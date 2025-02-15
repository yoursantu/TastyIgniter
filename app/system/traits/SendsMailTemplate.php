<?php

namespace System\Traits;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

trait SendsMailTemplate
{
    public function mailGetRecipients($type)
    {
        return [];
    }

    public function mailGetData()
    {
        return [];
    }

    public function mailSend($view, $recipientType = null)
    {
        $extraData = [];
        Event::fire('model.mail.beforeSend', [$this, &$extraData]);

        Mail::queue(
            $view,
            array_merge($extraData, $this->mailGetData()),
            is_callable($recipientType)
                ? $recipientType
                : $this->mailBuildMessage($recipientType)
        );
    }

    protected function mailBuildMessage($recipientType = null)
    {
        $recipients = $this->mailGetRecipients($recipientType);

        return function ($message) use ($recipients) {
            foreach ($recipients as $recipient) {
                [$email, $name] = $recipient;
                $message->to($email, $name);
            }
        };
    }
}
