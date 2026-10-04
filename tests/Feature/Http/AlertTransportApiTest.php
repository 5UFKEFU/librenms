<?php

namespace LibreNMS\Tests\Feature\Http;

use App\Models\AlertOperation;
use App\Models\AlertOperationTransportMap;
use App\Models\AlertTransport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LibreNMS\Tests\DBTestCase;

final class AlertTransportApiTest extends DBTestCase
{
    use DatabaseTransactions;

    private function adminToken(): string
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();

        return $user->createToken('test')->plainTextToken;
    }

    public function testApiTransportIsCreatedListedAndDeleted(): void
    {
        $token = $this->adminToken();

        $created = $this->json('POST', '/api/v0/alert/transports', [
            'name' => 'FebNMS Push',
            'type' => 'api',
            'config' => [
                'api-method' => 'POST',
                'api-url' => 'https://librenms.feb.sg/api/v1/alerts/webhook/x/y',
                'api-as-form' => true,
                'api-body' => "alertID={{ \$alert_id }}\nruleID={{ \$rule_id }}",
                'api-auth-password' => 'secret',
            ],
        ], ['X-Auth-Token' => $token])
            ->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('transports.0.transport_type', 'api')
            ->assertJsonPath('transports.0.transport_config.api-method', 'POST')
            ->assertJsonPath('transports.0.transport_config.api-auth-password', '********');
        $id = $created->json('transports.0.transport_id');

        $this->assertDatabaseHas('alert_transports', ['transport_id' => $id, 'transport_name' => 'FebNMS Push', 'transport_type' => 'api']);
        $this->assertSame('secret', AlertTransport::find($id)->transport_config['api-auth-password']);

        $this->json('GET', '/api/v0/alert/transports', [], ['X-Auth-Token' => $token])
            ->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('transports.0.transport_id', $id);

        $this->json('DELETE', "/api/v0/alert/transports/$id", [], ['X-Auth-Token' => $token])
            ->assertStatus(200);
        $this->assertDatabaseMissing('alert_transports', ['transport_id' => $id]);
    }

    public function testInvalidTransportsAreRejected(): void
    {
        $token = $this->adminToken();

        $this->json('POST', '/api/v0/alert/transports', ['name' => 'x', 'type' => 'nosuchtransport', 'config' => []], ['X-Auth-Token' => $token])
            ->assertStatus(422);
        $this->json('POST', '/api/v0/alert/transports', ['name' => 'x', 'type' => 'api', 'config' => ['api-method' => 'POST', 'api-url' => 'not a url']], ['X-Auth-Token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('alert_transports', 0);
    }

    public function testReadOnlyUsersCannotManageTransports(): void
    {
        /** @var User $user */
        $user = User::factory()->read()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->json('POST', '/api/v0/alert/transports', ['name' => 'x', 'type' => 'api', 'config' => ['api-url' => 'https://example.com']], ['X-Auth-Token' => $token])
            ->assertStatus(403);
    }

    public function testTransportIsAttachedToAndDetachedFromEverySegmentOfAnOperation(): void
    {
        $token = $this->adminToken();
        $transport = new AlertTransport;
        $transport->transport_name = 'Hook';
        $transport->transport_type = 'api';
        $transport->transport_config = ['api-url' => 'https://example.com'];
        $transport->save();
        $operation = AlertOperation::create(['name' => 'Default operation']);
        $problem = $operation->segments()->create(['position' => 0, 'operation_phase' => 'problem', 'escalation_step_from' => 1, 'start_in_seconds' => 0, 'step_duration_seconds' => 0]);
        $recovery = $operation->segments()->create(['position' => 1, 'operation_phase' => 'recovery', 'escalation_step_from' => 1, 'start_in_seconds' => 0, 'step_duration_seconds' => 0]);
        AlertOperationTransportMap::create(['segment_id' => $problem->id, 'transport_or_group_id' => $transport->transport_id, 'target_type' => 'single']);

        $this->json('GET', '/api/v0/alert/operations', [], ['X-Auth-Token' => $token])
            ->assertStatus(200)
            ->assertJsonPath('operations.0.id', $operation->id)
            ->assertJsonPath('operations.0.segments.0.transports.0.id', (string) $transport->transport_id);

        $this->json('POST', "/api/v0/alert/operations/{$operation->id}/transports", ['transport_id' => $transport->transport_id], ['X-Auth-Token' => $token])
            ->assertStatus(200)
            ->assertJsonPath('changed_segments', 1, 'only the recovery segment was missing it');
        $this->assertSame(2, AlertOperationTransportMap::where('transport_or_group_id', $transport->transport_id)->count());

        $this->json('POST', "/api/v0/alert/operations/{$operation->id}/transports", ['transport_id' => 999999], ['X-Auth-Token' => $token])
            ->assertStatus(404);

        $this->json('DELETE', "/api/v0/alert/operations/{$operation->id}/transports/{$transport->transport_id}", [], ['X-Auth-Token' => $token])
            ->assertStatus(200)
            ->assertJsonPath('changed_segments', 2);
        $this->assertSame(0, AlertOperationTransportMap::where('transport_or_group_id', $transport->transport_id)->count());
        $this->assertSame($recovery->id, $operation->segments()->where('operation_phase', 'recovery')->value('id'));
    }
}
