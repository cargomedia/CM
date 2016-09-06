<?php

class CM_Mail_Mailer extends Swift_Mailer implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    public function __construct(Swift_Transport $transport) {
        CM_Mail_Message::register();
        parent::__construct($transport);
    }

    public function send(Swift_Mime_Message $message, &$failedRecipients = null) {
        $failedRecipients = (array) $failedRecipients;
        $to = $message->getTo();
        if (empty($to)) {
            throw new CM_Exception_Invalid('No recipient specified');
        }

        $numSent = 0;
        $context = new CM_Log_Context();
        try {
            $numSent = parent::send($message, $failedRecipients);
        } catch (Exception $e) {
            $context->setException($e);
        }

        $this->getTransport()->stop();

        $succeeded = 0 !== $numSent && null !== $failedRecipients && 0 === count($failedRecipients);
        if (!$succeeded) {
            $context->setExtra([
                'message'          => [
                    'subject' => $message->getSubject(),
                    'from'    => $message->getFrom(),
                    'to'      => $message->getTo(),
                    'cc'      => $message->getCc(),
                    'bcc'     => $message->getBcc(),
                ],
                'failedRecipients' => $failedRecipients,
            ]);
            $this->getServiceManager()->getLogger()->error('Failed to send email', $context);
        }

        return $numSent;
    }

    public function createMessage($service = null) {
        $service = null === $service ? 'cm-message' : $service;
        return parent::createMessage($service);
    }
}