# 0048. A scheduled import of ERP orders

- Status: Accepted
- Date: 2026-11-06

## Context

The simulated ERP (ADR 0046) leaves one CSV file per order in a folder that an SFTP server shows, and the `Erp` context (ADR 0047) can already read a file and turn it into an order. What is missing is the part that does it by itself: look in the folder now and then, register what it finds, and never do the same thing twice.

Three problems come with it. How to make something run every so often. How to read from SFTP. And who the order belongs to, because every use case in the backend works for "the tenant of the signed-in user", and a job that a clock starts has no user, and the ERP does not say which tenant or which warehouse an order is for.

## Options considered

For the clock: a **cron line** that calls a console command, a **loop in a shell script**, or **Symfony Scheduler**, which sends messages through Messenger on a timetable written in PHP. For SFTP: **`ext-ssh2`**, which is a C extension that is hard to install and to test, or **phpseclib**, pure PHP. For the tenant: **the job names it in configuration**, or **the ERP's file names it**, which would let a file choose whose data it writes.

For the bus the schedule's message travels on: **the command bus**, whose middleware opens a transaction around the handler, or **a bus of its own without a transaction**.

## Decision

- **Symfony Scheduler** (`ErpSchedule`) sends `ImportErpOrders` every 30 seconds. A Compose service, `scheduler`, consumes the `scheduler_erp` transport. The timetable is code, it is tested like any other and it needs no cron in the image. The existing `worker` keeps consuming only `async`.
- **phpseclib** (`SftpErpOrderInbox`) reads the folder, with no PHP extension. The port is `ErpOrderInbox` in `Application`, with `pending`, `read`, `markProcessed` and `markRejected`, so the import is tested with a folder in memory and SFTP is the only code that knows SFTP. The host key is not checked in development; a real ERP needs it pinned before a password is sent.
- **A `job.bus` without transaction.** Its handlers implement `JobHandler`. If the import ran inside the transaction of the command bus, a failure in the registration of one order, even one caught inside, would mark the whole transaction as rollback-only and lose the orders already registered. The import registers each order through the command bus, which has its own transaction, and decides file by file what to do next.
- **Files are moved, never deleted.** An imported file goes to `processed/` and one that cannot become an order goes to `rejected/`, with a warning. They are the trail for whoever has to see what the ERP sent. A name that already exists in the target gets a timestamp, so nothing is overwritten.
- **Idempotent by the order, not by the file.** Registering an order whose reference exists answers `AlreadyRegistered` (OUT-01), and the file is moved all the same. A run that stops after registering and before moving the file ends the next time without a duplicate.
- **One bad file does not stop the others.** Anything the domain refuses, such as a malformed file, an unknown article or an order Outbound rejects, sends that file to `rejected/` and the run goes on. A failure of the SFTP server, the database or anything else that is not the file's fault stops the run, and the next tick starts over with the same files.
- **The tenant and the warehouse are in the configuration** (`ERP_TENANT_ID`, `ERP_WAREHOUSE_CODE`). `TenantRunner::run()` makes the tenant the current one while the import runs. Its implementation, `ScopedCurrentTenant`, is the `CurrentTenant` of the whole application: it answers with the tenant of the job if there is one and with the tenant of the signed-in user if not. A request never sets one, so a client still cannot ask for another tenant (ADR 0008). `WarehouseDirectory` gets `findByCode`, because the settings name the warehouse by the code staff use. With no tenant in the settings the import logs a warning and does nothing, so the stack starts without it.
- `app:erp:import-orders` runs the same handler once, for the person who wants to see it work or to run it from a real cron.

## Consequences

- Two dependencies, `phpseclib/phpseclib` and `symfony/scheduler`, and one more container when the ERP profile is up.
- Only one `scheduler` may run. Two would import the same files twice at the same moment; the second to register an order would get `AlreadyRegistered`, so no order is duplicated, but a file could be moved by one while the other reads it, and that run fails and tries again. A deployment with several instances needs a lock on the schedule.
- One tenant and one warehouse per ERP. Several ERPs, or a tenant with several, would take a list of settings or a table.
- A file that sits in `rejected/` is not retried. Fixing the cause, such as creating the product, and putting the file back by hand is how it is done today. The panel of E08-05 is the place to show it.
- The `Erp` context now also depends on `Warehouse` through its contract.
- The simulated ERP lets everyone write in its folder (it must, because two containers with different users share it), so `processed/` and `rejected/` can be created by the SFTP user.
