<?php

namespace Tests\Feature\Producers;

use App\Enums\DocumentType;
use App\Enums\PersonType;
use App\Enums\Role;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProducerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'person_type' => PersonType::Natural->value,
            'name' => 'María Fernanda Rojas',
            'document_type' => DocumentType::CitizenId->value,
            'document_number' => '1075234567',
            'phone' => '3105551234',
            'email' => 'maria@elparaiso.co',
            'municipality' => 'Palermo',
            'department' => 'Huila',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_admin_can_list_producers(): void
    {
        $producers = Producer::factory()->count(3)->create();
        Producer::factory()->inactive()->create();

        $response = $this->actingAs($this->admin)->get(route('producers.index'))->assertOk();

        foreach ($producers as $producer) {
            $response->assertSee($producer->name)->assertSee($producer->document_number);
        }

        $response->assertSee('Productores registrados')->assertSee('Nuevo productor');
    }

    public function test_admin_can_create_a_producer_with_normalized_data(): void
    {
        $this->actingAs($this->admin)
            ->post(route('producers.store'), $this->payload([
                'name' => '  María   Fernanda Rojas ',
                'document_number' => '1.075.234.567',
                'phone' => '(310) 555-1234',
                'email' => ' Maria@ElParaiso.CO ',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $producer = Producer::where('document_number', '1075234567')->firstOrFail();

        $this->assertSame('María Fernanda Rojas', $producer->name);
        $this->assertSame('3105551234', $producer->phone);
        $this->assertSame('maria@elparaiso.co', $producer->email);
        $this->assertSame(PersonType::Natural, $producer->person_type);
        $this->assertSame(DocumentType::CitizenId, $producer->document_type);
        $this->assertTrue($producer->is_active);
    }

    public function test_admin_can_create_a_juridical_producer_with_nit(): void
    {
        $this->actingAs($this->admin)
            ->post(route('producers.store'), $this->payload([
                'person_type' => PersonType::Juridical->value,
                'name' => 'Piscícola El Paraíso S.A.S.',
                'document_type' => DocumentType::TaxId->value,
                'document_number' => '900.123.456-7',
                'email' => '',
                'is_active' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $producer = Producer::where('document_number', '900123456-7')->firstOrFail();
        $this->assertNull($producer->email);
        $this->assertFalse($producer->is_active);
    }

    public function test_admin_can_view_a_producer_with_its_users(): void
    {
        $producer = Producer::factory()->create();
        $member = User::factory()->role(Role::Producer)->forProducer($producer)->create(['name' => 'Pedro Socio']);

        $this->actingAs($this->admin)
            ->get(route('producers.show', $producer))
            ->assertOk()
            ->assertSee($producer->name)
            ->assertSee($producer->phone)
            ->assertSee('Cuentas de usuario')
            ->assertSee($member->name);
    }

    public function test_admin_can_edit_a_producer(): void
    {
        $producer = Producer::factory()->create(['document_number' => '12345678']);

        $this->actingAs($this->admin)->get(route('producers.edit', $producer))->assertOk();

        $this->put(route('producers.update', $producer), $this->payload([
            'name' => 'Nombre Actualizado',
            'document_number' => '12345678',
            'municipality' => 'Neiva',
        ]))
            ->assertRedirect(route('producers.show', $producer))
            ->assertSessionHasNoErrors();

        $producer->refresh();
        $this->assertSame('Nombre Actualizado', $producer->name);
        $this->assertSame('Neiva', $producer->municipality);
        $this->assertSame('12345678', $producer->document_number);
    }

    public function test_admin_can_deactivate_a_producer_after_confirming(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->forProducer($producer)->create();

        $this->actingAs($this->admin)
            ->get(route('producers.deactivate.confirm', $producer))
            ->assertOk()
            ->assertSee('¿Desactivar a '.$producer->name.'?', false)
            ->assertSee('perderá el acceso');

        $this->patch(route('producers.deactivate', $producer))
            ->assertRedirect(route('producers.show', $producer));

        $this->assertFalse($producer->fresh()->is_active);
        $this->patch(route('producers.deactivate', $producer))->assertForbidden();
    }

    public function test_admin_can_activate_a_producer(): void
    {
        $producer = Producer::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->patch(route('producers.activate', $producer))
            ->assertRedirect(route('producers.show', $producer));

        $this->assertTrue($producer->fresh()->is_active);
        $this->patch(route('producers.activate', $producer))->assertForbidden();
    }

    public function test_search_by_name_document_and_phone_and_status_filter(): void
    {
        Producer::factory()->create(['name' => 'Acuícola La Represa', 'document_number' => '80123456', 'phone' => '3201112233']);
        Producer::factory()->inactive()->create(['name' => 'Tilapias del Huila', 'document_number' => '1098765432', 'phone' => '3159998877']);

        $this->actingAs($this->admin);

        $this->get(route('producers.index', ['q' => 'represa']))
            ->assertSee('Acuícola La Represa')->assertDontSee('Tilapias del Huila');

        $this->get(route('producers.index', ['q' => '1.098.765']))
            ->assertSee('Tilapias del Huila')->assertDontSee('Acuícola La Represa');

        $this->get(route('producers.index', ['q' => '320 111']))
            ->assertSee('Acuícola La Represa')->assertDontSee('Tilapias del Huila');

        $this->get(route('producers.index', ['status' => 'inactive']))
            ->assertSee('Tilapias del Huila')->assertDontSee('Acuícola La Represa');

        $this->get(route('producers.index', ['status' => 'active']))
            ->assertSee('Acuícola La Represa')->assertDontSee('Tilapias del Huila');

        $this->get(route('producers.index', ['q' => 'no-existe']))
            ->assertSee('No hay productores que coincidan');
    }

    public function test_producers_are_paginated(): void
    {
        foreach (range(1, 17) as $i) {
            Producer::factory()->create(['name' => sprintf('Productor %02d', $i)]);
        }

        $this->actingAs($this->admin);

        $this->get(route('producers.index'))
            ->assertSee('Productor 15')->assertDontSee('Productor 16')
            ->assertSee('Mostrando 1–15 de 17');

        $this->get(route('producers.index', ['page' => 2]))
            ->assertSee('Productor 16')->assertSee('Productor 17')->assertDontSee('Productor 01');
    }

    public function test_listing_does_not_run_n_plus_one_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('producers.index'))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        Producer::factory()->count(2)->create()->each(fn ($p) => User::factory()->forProducer($p)->create());
        $few = $count();

        Producer::factory()->count(10)->create()->each(fn ($p) => User::factory()->forProducer($p)->create());
        $many = $count();

        $this->assertSame($few, $many);
    }

    public function test_duplicate_document_is_rejected_on_create_and_update(): void
    {
        Producer::factory()->create(['document_number' => '1075234567']);
        $other = Producer::factory()->create(['document_number' => '55555555']);

        $this->actingAs($this->admin)
            ->post(route('producers.store'), $this->payload(['document_number' => '1.075.234.567']))
            ->assertSessionHasErrors(['document_number' => 'Ya existe un productor registrado con este número de documento.']);

        $this->put(route('producers.update', $other), $this->payload(['document_number' => '1075234567']))
            ->assertSessionHasErrors('document_number');

        $this->assertSame(1, Producer::where('document_number', '1075234567')->count());
        $this->assertSame('55555555', $other->fresh()->document_number);
    }

    public function test_invalid_data_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('producers.store'), [
                'person_type' => 'otro',
                'name' => 'A',
                'document_type' => 'XX',
                'document_number' => '',
                'phone' => 'abc',
                'email' => 'no-es-correo',
                'municipality' => '',
                'department' => 'Texas',
                'is_active' => 'tal vez',
            ])
            ->assertSessionHasErrors([
                'person_type', 'name', 'document_type', 'document_number', 'phone',
                'email', 'municipality', 'department', 'is_active',
            ]);

        $this->assertSame(0, Producer::count());
    }

    public function test_document_must_match_person_and_document_type(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('producers.store'), $this->payload([
            'person_type' => PersonType::Juridical->value,
            'document_type' => DocumentType::CitizenId->value,
        ]))->assertSessionHasErrors(['document_type' => 'Una persona jurídica debe identificarse con NIT.']);

        $this->post(route('producers.store'), $this->payload(['document_number' => 'ABC12345']))
            ->assertSessionHasErrors(['document_number' => 'El número de documento no es válido para Cédula de ciudadanía: debe tener entre 5 y 10 dígitos.']);

        $this->post(route('producers.store'), $this->payload([
            'document_type' => DocumentType::Passport->value,
            'document_number' => 'ab12345',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('producers', ['document_number' => 'AB12345']);
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        $this->actingAs($this->admin)
            ->post(route('producers.store'), [])
            ->assertSessionHasErrors([
                'person_type' => 'El campo tipo de persona es obligatorio.',
                'name' => 'El campo nombre es obligatorio.',
                'document_type' => 'El campo tipo de documento es obligatorio.',
                'document_number' => 'El campo número de documento es obligatorio.',
                'phone' => 'El campo teléfono es obligatorio.',
                'municipality' => 'El campo municipio es obligatorio.',
                'department' => 'El campo departamento es obligatorio.',
                'is_active' => 'El campo estado es obligatorio.',
            ]);

        $this->post(route('producers.store'), $this->payload(['phone' => '12', 'department' => 'Texas', 'email' => 'x']))
            ->assertSessionHasErrors([
                'phone' => 'El teléfono debe tener entre 7 y 15 dígitos y puede comenzar con +.',
                'department' => 'El valor seleccionado en departamento no es válido.',
                'email' => 'El campo correo electrónico debe ser un correo electrónico válido.',
            ]);
    }

    public function test_producers_cannot_be_deleted(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->forProducer($producer)->create();

        $this->actingAs($this->admin)
            ->delete('/productores/'.$producer->id)
            ->assertStatus(405);

        $this->expectException(QueryException::class);
        $producer->delete();
    }
}
