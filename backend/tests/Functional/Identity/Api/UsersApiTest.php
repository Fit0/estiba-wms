<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Api;

use App\Identity\Domain\Role;
use App\Tests\Functional\Support\ApiTestCase;
use App\Tests\Unit\Support\Ids;

/**
 * Managing the users of a tenant: listing, creating, changing roles, disabling and enabling (IDN-04, IDN-11, IDN-12).
 */
final class UsersApiTest extends ApiTestCase
{
    private const string PASSWORD = 'una-contrasena-larga-1';

    public function testAnAdminCreatesAUserOfItsOwnTenant(): void
    {
        $created = $this->createUser('carlos@marea-moda.example', ['operator']);

        $this->assertStatus(201);
        self::assertSame('carlos@marea-moda.example', $created['email']);
        self::assertSame(['operator'], $created['roles']);
        self::assertSame('active', $created['status']);
        self::assertArrayNotHasKey('password', $created);
        self::assertArrayNotHasKey('passwordHash', $created);
        self::assertSame($this->id($created), $this->id($this->request('GET', '/api/users/'.$this->id($created))));
    }

    public function testListsTheUsersOfTheTenantByEmailWithTheirTotal(): void
    {
        $this->createUser('zoe@marea-moda.example', ['operator']);
        $this->createUser('ana@marea-moda.example', ['supervisor']);

        $first = $this->request('GET', '/api/users', query: ['itemsPerPage' => 2]);

        self::assertCount(2, $first);
        self::assertSame('3', $this->client->getResponse()->headers->get('X-Total-Count'));
        $all = $this->emails($this->request('GET', '/api/users'));
        $sorted = $all;
        sort($sorted);
        self::assertSame($sorted, $all);
        self::assertCount(3, $all, 'The admin of the test and the two users.');
    }

    public function testEachTenantSeesOnlyItsOwnUsers(): void
    {
        $other = Ids::next();
        $theirs = $this->request('POST', '/api/users', ['email' => 'theirs@other.example', 'password' => self::PASSWORD, 'roles' => ['operator']], $other);
        $mine = $this->createUser('mine@marea-moda.example', ['operator']);

        self::assertNotContains('theirs@other.example', $this->emails($this->request('GET', '/api/users')));

        $this->request('GET', '/api/users/'.$this->id($theirs));
        $this->assertStatus(404);
        $this->request('POST', '/api/users/'.$this->id($theirs).'/disable');
        $this->assertStatus(404);
        $this->request('POST', '/api/users/'.$this->id($theirs).'/roles', ['roles' => ['admin']]);
        $this->assertStatus(404);
        $this->request('GET', '/api/users/'.$this->id($mine), tenant: $other);
        $this->assertStatus(404);
    }

    public function testRefusesAnEmailThatIsAlreadyUsedAnywhere(): void
    {
        $this->createUser('carlos@marea-moda.example', ['operator']);

        $problem = $this->createUser('CARLOS@marea-moda.example', ['operator']);

        $this->assertProblem(409, 'identity.email_already_used', $problem);
    }

    public function testRefusesAPasswordThatIsTooShortAndARoleThatDoesNotExist(): void
    {
        $this->assertProblem(422, 'identity.password_invalid', $this->request('POST', '/api/users', ['email' => 'a@marea-moda.example', 'password' => 'short', 'roles' => ['operator']]));
        $this->assertProblem(422, 'identity.role_invalid', $this->createUser('b@marea-moda.example', ['boss']));
        $this->assertProblem(422, 'identity.roles_required', $this->createUser('c@marea-moda.example', []));
    }

    public function testReplacesTheRolesOfAUser(): void
    {
        $user = $this->createUser('carlos@marea-moda.example', ['operator']);

        $changed = $this->request('POST', '/api/users/'.$this->id($user).'/roles', ['roles' => ['supervisor', 'operator']]);

        $this->assertStatus(200);
        self::assertSame(['supervisor', 'operator'], $changed['roles']);
        self::assertSame(['supervisor', 'operator'], $this->request('GET', '/api/users/'.$this->id($user))['roles']);
    }

    // IDN-04
    public function testRefusesToLeaveAUserWithoutRolesOrWithARoleThatDoesNotExist(): void
    {
        $user = $this->createUser('carlos@marea-moda.example', ['operator']);

        $this->assertProblem(422, 'identity.roles_required', $this->request('POST', '/api/users/'.$this->id($user).'/roles', ['roles' => []]));
        $this->assertProblem(422, 'identity.role_invalid', $this->request('POST', '/api/users/'.$this->id($user).'/roles', ['roles' => ['boss']]));
        self::assertSame(['operator'], $this->request('GET', '/api/users/'.$this->id($user))['roles']);
    }

    public function testDisablesAndEnablesAUser(): void
    {
        $user = $this->createUser('carlos@marea-moda.example', ['operator']);
        $id = $this->id($user);

        self::assertSame('disabled', $this->request('POST', '/api/users/'.$id.'/disable')['status']);
        $this->assertProblem(409, 'identity.user_already_disabled', $this->request('POST', '/api/users/'.$id.'/disable'));
        self::assertSame('active', $this->request('POST', '/api/users/'.$id.'/enable')['status']);
        $this->assertProblem(409, 'identity.user_not_disabled', $this->request('POST', '/api/users/'.$id.'/enable'));
    }

    // IDN-12
    public function testAnAdminCannotDisableItselfNorGiveUpItsOwnAdminRole(): void
    {
        $me = $this->id($this->request('GET', '/api/me'));

        $this->assertProblem(422, 'identity.cannot_change_own_access', $this->request('POST', '/api/users/'.$me.'/disable'));
        $this->assertProblem(422, 'identity.cannot_change_own_access', $this->request('POST', '/api/users/'.$me.'/roles', ['roles' => ['supervisor']]));
        self::assertSame(['admin'], $this->request('GET', '/api/users/'.$me)['roles']);
        self::assertSame('active', $this->request('GET', '/api/users/'.$me)['status']);
    }

    public function testAnAdminMayStillAddRolesToItself(): void
    {
        $me = $this->id($this->request('GET', '/api/me'));

        $changed = $this->request('POST', '/api/users/'.$me.'/roles', ['roles' => ['admin', 'operator']]);

        $this->assertStatus(200);
        self::assertSame(['admin', 'operator'], $changed['roles']);
    }

    // IDN-11: with two admins, one can take the other off; the one left is then the last
    public function testAnAdminCanBeDisabledByAnotherAdmin(): void
    {
        $second = $this->createUser('second@marea-moda.example', ['admin']);

        $this->request('POST', '/api/users/'.$this->id($second).'/disable');

        $this->assertStatus(200);
    }

    public function testChangingRolesLeavesATraceInTheAuditTrail(): void
    {
        $user = $this->createUser('carlos@marea-moda.example', ['operator']);
        $this->request('POST', '/api/users/'.$this->id($user).'/roles', ['roles' => ['supervisor']]);

        $entries = $this->request('GET', '/api/audit-log', query: ['subjectId' => $this->id($user)]);

        $types = array_column($entries, 'eventType');
        self::assertContains('identity.user_registered', $types);
        self::assertContains('identity.user_roles_changed', $types);
        foreach ($entries as $entry) {
            self::assertIsArray($entry);
            self::assertStringNotContainsString('carlos@', json_encode($entry['data'] ?? [], \JSON_THROW_ON_ERROR), 'The trail holds no email address.');
        }
    }

    public function testOnlyAnAdminManagesUsers(): void
    {
        $user = $this->createUser('carlos@marea-moda.example', ['operator']);
        $id = $this->id($user);

        foreach ([Role::Operator, Role::Supervisor] as $role) {
            $this->role = $role;
            $this->request('GET', '/api/users');
            $this->assertStatus(403);
            $this->request('POST', '/api/users', ['email' => 'x@marea-moda.example', 'password' => self::PASSWORD, 'roles' => ['operator']]);
            $this->assertStatus(403);
            $this->request('POST', '/api/users/'.$id.'/disable');
            $this->assertStatus(403);
            $this->request('POST', '/api/users/'.$id.'/roles', ['roles' => ['admin']]);
            $this->assertStatus(403);
        }

        $this->role = Role::Admin;
        $this->request('GET', '/api/users', tenant: '');
        $this->assertStatus(401);
    }

    public function testTheOpenApiDocumentHasTheUsers(): void
    {
        $paths = $this->openApiPaths();

        self::assertArrayHasKey('/api/users', $paths);
        self::assertArrayHasKey('/api/users/{id}/roles', $paths);
        self::assertArrayHasKey('/api/users/{id}/disable', $paths);
    }

    /**
     * @param list<string> $roles
     *
     * @return array<array-key, mixed>
     */
    private function createUser(string $email, array $roles): array
    {
        return $this->request('POST', '/api/users', ['email' => $email, 'password' => self::PASSWORD, 'roles' => $roles]);
    }

    /**
     * @param array<array-key, mixed> $users
     *
     * @return list<string>
     */
    private function emails(array $users): array
    {
        $emails = [];
        foreach ($users as $user) {
            self::assertIsArray($user);
            self::assertIsString($user['email'] ?? null);
            $emails[] = $user['email'];
        }

        return $emails;
    }
}
