<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Feedback extends Mailable
{
    use Queueable, SerializesModels;


    private $rate;
    private $shopName;
    private $feedbackText;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($rate, $shopName, $feedbackText)
    {
       $this->rate = $rate;
       $this->shopName = $shopName;
       $this->feedbackText = $feedbackText;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.feedback')
            ->to(config('mail.admin_mail'))
            ->subject('New Send as Gift Feedback')
            ->with([
                'rate' => $this->rate,
                'shopName' => $this->shopName,
                'feedbackText' => $this->feedbackText,
            ]);
    }
}
