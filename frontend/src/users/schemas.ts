import { z } from 'zod';
import { roles } from '../auth/roles';
import type { MessageKey } from '../i18n';

/**
 * What the forms of the users accept. The rules are the ones of the domain (IDN-02, IDN-03, IDN-04);
 * the API repeats them and has the last word. The texts of the errors are keys of the catalog.
 */
const message = (key: MessageKey): string => key;

const roleList = z
  .array(z.enum(roles))
  .min(1, message('users.error.roles'))
  .transform((chosen) => roles.filter((role) => chosen.includes(role)));

export const userFormSchema = z.object({
  email: z
    .string()
    .trim()
    .min(1, message('form.required'))
    .max(254, message('form.tooLong'))
    .pipe(z.email(message('users.error.email'))),
  password: z
    .string()
    .min(12, message('users.error.password'))
    .max(72, message('users.error.password')),
  roles: roleList,
});

export const rolesFormSchema = z.object({ roles: roleList });

export type UserFormInput = z.input<typeof userFormSchema>;
export type UserFormOutput = z.output<typeof userFormSchema>;
export type RolesFormInput = z.input<typeof rolesFormSchema>;
export type RolesFormOutput = z.output<typeof rolesFormSchema>;
