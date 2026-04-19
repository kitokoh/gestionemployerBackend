<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AttendanceAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly Employee $recipient,
        public readonly string $alertType,
        public readonly string $thresholdTime,
        public readonly array $items,
        public readonly string $localDate,
    ) {}

    public function build(): self
    {
        $subjectPrefix = $this->alertType === 'missing_check_out'
            ? 'Alerte sorties manquantes'
            : 'Alerte pointages manquants';

        return $this
            ->subject("{$subjectPrefix} - {$this->company->name}")
            ->view('emails.attendance-alert');
    }
}
