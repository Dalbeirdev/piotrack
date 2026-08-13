<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    public function index(): Response
    {
        $organization = $this->currentOrganization->get();

        return Inertia::render('billing/invoices', [
            'invoices' => $organization->invoices()
                ->latest('id')
                ->paginate(20)
                ->through(fn (Invoice $i) => [
                    'id' => $i->id,
                    'number' => $i->number,
                    'status' => $i->status,
                    'total' => $i->total,
                    'currency' => $i->currency,
                    'created_at' => $i->created_at,
                    'paid_at' => $i->paid_at,
                ]),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        // Tenant ownership check (invoices are not globally tenant-scoped).
        abort_unless($invoice->organization_id === $this->currentOrganization->id(), 404);

        $invoice->load('lineItems');

        return Inertia::render('billing/invoice', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'subtotal' => $invoice->subtotal,
                'discount' => $invoice->discount,
                'tax' => $invoice->tax,
                'total' => $invoice->total,
                'amount_paid' => $invoice->amount_paid,
                'period_start' => $invoice->period_start,
                'period_end' => $invoice->period_end,
                'created_at' => $invoice->created_at,
                'paid_at' => $invoice->paid_at,
                'line_items' => $invoice->lineItems->map(fn ($li) => [
                    'description' => $li->description,
                    'quantity' => $li->quantity,
                    'unit_amount' => $li->unit_amount,
                    'amount' => $li->amount,
                ]),
            ],
        ]);
    }
}
