<?php

namespace App\Mail;

use App\Models\Daily;
use App\Models\Reserve;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** E-mail enviado ao hóspede quando a reserva é criada. */
class ReserveConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Reserve $reserve) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reserva {$this->reserve->code} confirmada - {$this->reserve->hotel->name}",
        );
    }

    public function content(): Content
    {
        $r = $this->reserve;
        $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
        $guest = $r->guests->first();

        $dailies = $r->dailies
            ->sortBy(fn (Daily $d) => $d->date->toDateString())
            ->map(fn (Daily $d) => '<tr><td>'.$d->date->format('d/m/Y').'</td><td align="right">'.$money($d->value - $d->discount).'</td></tr>')
            ->implode('');

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;max-width:560px">'
            .'<h2 style="margin:0 0 8px">Sua reserva está confirmada</h2>'
            .'<p>Olá, '.e($guest?->name ?? 'hóspede').'! Obrigado por escolher o <strong>'.e($r->hotel->name).'</strong>.</p>'
            .'<p style="font-size:18px">Localizador: <strong>'.e($r->code).'</strong></p>'
            .'<table cellpadding="4" style="border-collapse:collapse">'
            .'<tr><td>Acomodação</td><td><strong>'.e($r->room->name).'</strong></td></tr>'
            .'<tr><td>Check-in</td><td>'.$r->check_in->format('d/m/Y').'</td></tr>'
            .'<tr><td>Check-out</td><td>'.$r->check_out->format('d/m/Y').'</td></tr>'
            .'<tr><td>Hóspedes</td><td>'.$r->guests->count().'</td></tr>'
            .'</table>'
            .'<h3 style="margin:16px 0 4px">Diárias</h3>'
            .'<table cellpadding="4" style="border-collapse:collapse;min-width:240px">'.$dailies.'</table>'
            .'<table cellpadding="4" style="border-collapse:collapse;min-width:240px;margin-top:8px">'
            .'<tr><td>Subtotal</td><td align="right">'.$money($r->subtotal).'</td></tr>'
            .((float) $r->discount > 0 ? '<tr><td>Descontos</td><td align="right">- '.$money($r->discount).'</td></tr>' : '')
            .((float) $r->fees > 0 ? '<tr><td>Taxas</td><td align="right">'.$money($r->fees).'</td></tr>' : '')
            .'<tr><td><strong>Total</strong></td><td align="right"><strong>'.$money($r->total).'</strong></td></tr>'
            .'<tr><td>Pago</td><td align="right">'.$money($r->paidAmount()).'</td></tr>'
            .'<tr><td>Saldo</td><td align="right">'.$money($r->balance()).'</td></tr>'
            .'</table>'
            .'<p style="color:#666;margin-top:16px">Guarde o localizador: com ele e o seu sobrenome você consulta a reserva a qualquer momento.</p>'
            .'</div>';

        return new Content(htmlString: $html);
    }
}
