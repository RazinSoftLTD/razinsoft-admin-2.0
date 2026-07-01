<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderFulfilledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function build(): self
    {
        $order = $this->order->loadMissing('items', 'invoice', 'user');

        return $this->subject("Your RazinSoft order {$order->order_number} is ready")
            ->html($this->body($order));
    }

    private function body(Order $order): string
    {
        $rows = $order->items->map(fn ($i) => "<li><strong>{$i->product_name}</strong> — ".($i->plan_name ?? 'License').'</li>')->implode('');

        return <<<HTML
            <div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#1f2937">
              <h2 style="color:#2563eb">Thank you, {$order->user->name}!</h2>
              <p>Your order <strong>{$order->order_number}</strong> has been paid and fulfilled.</p>
              <p>You now have access to:</p>
              <ul>{$rows}</ul>
              <p>Your invoice, license keys/files and source-code downloads are available in your
              <a href="{$this->dashUrl()}">account dashboard</a>.</p>
              <p style="color:#6b7280;font-size:13px">Invoice: {$order->invoice?->invoice_number}</p>
            </div>
            HTML;
    }

    private function dashUrl(): string
    {
        return rtrim(config('services.frontend_url', config('app.frontend_url', 'http://localhost:3000')), '/').'/dashboard';
    }
}
