<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TenantProvisioningHealthCheckService
{
    /**
     * @return array{
     *     ok: bool,
     *     connection: string,
     *     can_connect: bool,
     *     can_read_grants: bool,
     *     has_required_privilege: bool,
     *     required_privileges: array<int, string>,
     *     grants: array<int, string>,
     *     errors: array<int, string>
     * }
     */
    public function evaluate(): array
    {
        $connectionName = (string) config('tenancy.provisioning_connection', 'mysql_provisioning');
        $requiredPrivileges = ['CREATE', 'ALL PRIVILEGES', 'SUPER'];

        $result = [
            'ok' => false,
            'connection' => $connectionName,
            'can_connect' => false,
            'can_read_grants' => false,
            'has_required_privilege' => false,
            'required_privileges' => $requiredPrivileges,
            'grants' => [],
            'errors' => [],
        ];

        try {
            $connection = DB::connection($connectionName);
            $connection->select('SELECT 1');
            $result['can_connect'] = true;

            $grantRows = $connection->select('SHOW GRANTS FOR CURRENT_USER()');
            $grants = $this->flattenGrantRows($grantRows);
            $result['grants'] = $grants;
            $result['can_read_grants'] = true;

            $hasRequiredPrivilege = $this->hasRequiredProvisioningPrivilege($grants);
            $result['has_required_privilege'] = $hasRequiredPrivilege;
            $result['ok'] = $hasRequiredPrivilege;

            if (! $hasRequiredPrivilege) {
                $result['errors'][] = 'Provisioning user grants are missing CREATE, ALL PRIVILEGES, or SUPER on *.*.';
            }

            return $result;
        } catch (Throwable $exception) {
            $result['errors'][] = $exception->getMessage();

            return $result;
        }
    }

    public function assertHealthy(): void
    {
        $result = $this->evaluate();

        if ($result['ok']) {
            return;
        }

        $errorDetails = implode('; ', $result['errors']);
        $grantDetails = $result['grants'] === [] ? 'none returned' : implode(' || ', $result['grants']);

        throw new RuntimeException(sprintf(
            'Tenant provisioning health check failed for connection "%s". Errors: %s. Grants: %s. Configure DB_PROVISIONING_* credentials with CREATE (or ALL PRIVILEGES / SUPER).',
            $result['connection'],
            $errorDetails === '' ? 'unknown failure' : $errorDetails,
            $grantDetails
        ));
    }

    /**
     * @param array<int, object> $rows
     * @return array<int, string>
     */
    private function flattenGrantRows(array $rows): array
    {
        $grants = [];

        foreach ($rows as $row) {
            foreach (get_object_vars($row) as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $grants[] = $value;
                }
            }
        }

        return $grants;
    }

    /**
     * @param array<int, string> $grants
     */
    private function hasRequiredProvisioningPrivilege(array $grants): bool
    {
        foreach ($grants as $grant) {
            $grantParts = $this->parseGrantStatement($grant);
            if ($grantParts === null) {
                continue;
            }

            if (! $this->isGlobalScopeGrant($grantParts['scope'])) {
                continue;
            }

            $privileges = $grantParts['privileges'];
            if (in_array('ALL PRIVILEGES', $privileges, true)) {
                return true;
            }

            if (in_array('SUPER', $privileges, true)) {
                return true;
            }

            if (in_array('CREATE', $privileges, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{privileges: array<int, string>, scope: string}|null
     */
    private function parseGrantStatement(string $grant): ?array
    {
        if (preg_match('/^\s*GRANT\s+(.+?)\s+ON\s+(.+?)\s+TO\s+/i', $grant, $matches) !== 1) {
            return null;
        }

        $privileges = array_values(array_filter(array_map(
            static fn (string $token): string => strtoupper(trim(preg_replace('/\s+/', ' ', $token) ?? '')),
            explode(',', $matches[1])
        ), static fn (string $token): bool => $token !== ''));

        return [
            'privileges' => $privileges,
            'scope' => trim($matches[2]),
        ];
    }

    private function isGlobalScopeGrant(string $scope): bool
    {
        $normalizedScope = strtoupper(str_replace(['`', ' '], '', $scope));

        return $normalizedScope === '*.*';
    }
}
