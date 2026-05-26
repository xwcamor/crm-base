<?php

namespace Tests\Feature\BusinessManagement;

use App\Jobs\BusinessManagement\Customers\GenerateCustomersCsvJob;
use App\Jobs\BusinessManagement\Customers\GenerateCustomersExcelJob;
use Illuminate\Support\Facades\Bus;

/**
 * Endpoints de export del modulo Customers. Verifica que los formatos async
 * (CSV/Excel/PDF/Word) despachan el Job correcto al queue. El detalle del
 * contenido se cubre en tests dedicados al Job (no estan aca).
 */
class CustomerExportTest extends CustomerTestCase
{
    public function test_export_excel_queues_job(): void
    {
        Bus::fake();
        $this->actingAsTenantAdmin(1);

        $response = $this->post(route('business_management.customers.export_excel'), [
            'columns' => ['id', 'name',
                'cod', // @scaffold:remove-line
            ],
            'scope'   => 'all',
        ]);

        $response->assertRedirect();
        Bus::assertDispatched(GenerateCustomersExcelJob::class);
    }

    public function test_export_csv_queues_job(): void
    {
        Bus::fake();
        $this->actingAsTenantAdmin(1);

        $response = $this->post(route('business_management.customers.export_csv'), [
            'columns' => ['id', 'name',
                'cod', // @scaffold:remove-line
            ],
            'scope'   => 'all',
        ]);

        $response->assertRedirect();
        Bus::assertDispatched(GenerateCustomersCsvJob::class);
    }

    public function test_import_template_downloads_xlsx(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->get(route('business_management.customers.import_template'));

        $response->assertOk();
        // Maatwebsite\Excel manda el archivo como attachment.
        $contentType = $response->headers->get('content-type', '');
        $disposition = $response->headers->get('content-disposition', '');
        $this->assertTrue(
            str_contains($disposition, 'attachment') || str_contains($contentType, 'spreadsheet'),
            'La respuesta debe ser una descarga (attachment o xlsx).'
        );
    }
}
