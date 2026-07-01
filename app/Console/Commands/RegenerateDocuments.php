<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\License;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RegenerateDocuments extends Command
{
    protected $signature = 'documents:regenerate {--type=all : all|invoices|licenses}';

    protected $description = 'Re-render every invoice and/or license PDF in the current design (run after changing a template).';

    public function handle(): int
    {
        $type = $this->option('type');

        if (in_array($type, ['all', 'invoices'], true)) {
            $this->regenerateInvoices();
        }
        if (in_array($type, ['all', 'licenses'], true)) {
            $this->regenerateLicenses();
        }

        return self::SUCCESS;
    }

    private function regenerateInvoices(): void
    {
        $invoices = Invoice::with('order.items', 'order.user')->get();
        $count = 0;

        foreach ($invoices as $invoice) {
            $order = $invoice->order;
            if (! $order) {
                continue;
            }

            $pdf = Pdf::loadView('invoices.pdf', [
                'order' => $order,
                'invoice' => $invoice,
                'billing' => (array) ($order->billing ?? []),
            ])->setPaper('a4');

            $path = "invoices/{$invoice->invoice_number}.pdf";
            Storage::disk('local')->put($path, $pdf->output());
            $invoice->update(['pdf_path' => $path]);
            $count++;
        }

        $this->info("Regenerated {$count} invoice PDF(s).");
    }

    private function regenerateLicenses(): void
    {
        $licenses = License::with('orderItem.order.user', 'orderItem.plan')->get();
        $count = 0;

        foreach ($licenses as $license) {
            $item = $license->orderItem;
            $order = $item?->order;
            if (! $item || ! $order) {
                continue;
            }

            $perks = $item->plan?->perks;
            $perks = is_array($perks) ? $perks : $this->perksFromTerms((string) $license->terms);

            $pdf = Pdf::loadView('licenses.pdf', [
                'order' => $order->loadMissing('user'),
                'item' => $item,
                'license' => $license,
                'perks' => $perks,
            ])->setPaper('a4');

            $newPath = 'licenses/'.$license->license_key.'.pdf';
            Storage::disk('local')->put($newPath, $pdf->output());

            if ($license->file_path && $license->file_path !== $newPath) {
                Storage::disk('local')->delete($license->file_path);
            }
            $license->update(['file_path' => $newPath]);
            $count++;
        }

        $this->info("Regenerated {$count} license PDF(s).");
    }

    /** Fallback: pull bullet perks out of the stored terms text (when the plan was deleted). */
    private function perksFromTerms(string $terms): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $terms))
            ->map(fn ($l) => ltrim(trim($l), '- '))
            ->filter(fn ($l) => $l !== '' && ! str_starts_with($l, 'Plan:'))
            ->values()->all();
    }
}
