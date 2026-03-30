<?php

namespace App\Console\Commands;

use App\Services\TenantProvisioningHealthCheckService;
use Illuminate\Console\Command;

class TenancyHealthCheckCommand extends Command
{
    protected $signature = 'tenancy:health-check';

    protected $description = 'Validate tenant provisioning database connectivity and grants';

    public function handle(TenantProvisioningHealthCheckService $healthCheckService): int
    {
        $result = $healthCheckService->evaluate();

        $this->line('Tenant provisioning health check');
        $this->line('Connection: '.$result['connection']);
        $this->line('Can connect: '.($result['can_connect'] ? 'yes' : 'no'));
        $this->line('Can read grants: '.($result['can_read_grants'] ? 'yes' : 'no'));
        $this->line('Has required privilege: '.($result['has_required_privilege'] ? 'yes' : 'no'));

        if ($result['grants'] !== []) {
            $this->line('Grants:');
            foreach ($result['grants'] as $grant) {
                $this->line(' - '.$grant);
            }
        }

        if ($result['errors'] !== []) {
            $this->error('Errors:');
            foreach ($result['errors'] as $error) {
                $this->error(' - '.$error);
            }
        }

        if ($result['ok']) {
            $this->info('PASS: provisioning connection and privileges are healthy.');

            return self::SUCCESS;
        }

        $this->error('FAIL: provisioning connection privileges are not sufficient.');
        $this->line('Expected at least one of: CREATE DATABASE, ALL PRIVILEGES, or SUPER on *.*');

        return self::FAILURE;
    }
}
