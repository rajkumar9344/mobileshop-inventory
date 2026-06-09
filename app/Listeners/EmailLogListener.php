<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Events\MailFailed;
use App\Models\EmailLog;
use Illuminate\Log\Logger;

class EmailLogListener
{
    public function handle($event)
    {
        if ($event instanceof MessageSent) {
            $this->handleMessageSent($event);
        } elseif ($event instanceof MailFailed) {
            $this->handleMailFailed($event);
        }
    }

    /**
     * Handle the event when a message is sent.
     */
    public function handleMessageSent(MessageSent $event)
    {
        $logId = $this->extractLogIdFromMessage($event->message);
        if (!$logId) {
            return;
        }

        $log = EmailLog::find($logId);
        if (!$log) return;

        $log->status = 'sent';
        $log->sent_at = now();
        // Try to persist message id and headers in a robust, Mailer-implementation agnostic way
        $messageId = $this->extractMessageId($event->message);
        if ($messageId) {
            $log->message_id = $messageId;
        }

        $headers = $this->extractHeaders($event->message);
        if (!empty($headers)) {
            $log->headers = json_encode($headers);
        }

        $log->save();
    }

    /**
     * Handle failed mail event
     */
    public function handleMailFailed(MailFailed $event)
    {
        $logId = $this->extractLogIdFromMessage($event->message);
        if (!$logId) return;

        $log = EmailLog::find($logId);
        if (!$log) return;

        $log->status = 'failed';
        $log->error = $event->exception ? $event->exception->getMessage() : 'Unknown failure';
        $log->save();
    }

    /**
     * Try to extract our X-Email-Log-Id header from the message in a robust way.
     */
    protected function extractLogIdFromMessage($message)
    {
        try {
            // If the message exposes a getHeaderLine method (Symfony Mime Email), use it
            if (method_exists($message, 'getHeaderLine')) {
                $v = $message->getHeaderLine('X-Email-Log-Id');
                if (!empty($v)) return $v;
            }

            // If the message exposes getHeaders (Swift_Message or similar), try to read header
            if (method_exists($message, 'getHeaders')) {
                $headers = $message->getHeaders();
                if (method_exists($headers, 'get')) {
                    $h = $headers->get('X-Email-Log-Id');
                    if ($h) {
                        if (method_exists($h, 'getFieldBody')) return $h->getFieldBody();
                        if (method_exists($h, 'getBodyAsString')) return $h->getBodyAsString();
                        // Try casting to string
                        try { return (string) $h; } catch (\Throwable $__e) {}
                    }
                }
            }

            // Fallback: convert whole message to string and parse header line
            if (method_exists($message, 'toString')) {
                $raw = $message->toString();
            } elseif (method_exists($message, '__toString')) {
                $raw = (string) $message;
            } else {
                $raw = null;
            }

            if (!empty($raw)) {
                // Look for header like: X-Email-Log-Id: 123
                if (preg_match('/^X-Email-Log-Id:\s*(.+)$/mi', $raw, $m)) {
                    return trim($m[1]);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * Extract message id using multiple strategies
     */
    protected function extractMessageId($message)
    {
        try {
            if (method_exists($message, 'getMessageId')) {
                $id = $message->getMessageId();
                if (!empty($id)) return $id;
            }

            // Symfony Mime Email: header may be available via getHeaderLine
            if (method_exists($message, 'getHeaderLine')) {
                $id = $message->getHeaderLine('Message-Id');
                if (!empty($id)) return $id;
                $id = $message->getHeaderLine('Message-ID');
                if (!empty($id)) return $id;
            }

            if (method_exists($message, 'getHeaders')) {
                $headers = $message->getHeaders();
                if (method_exists($headers, 'get')) {
                    $h = $headers->get('Message-Id') ?: $headers->get('Message-ID');
                    if ($h) {
                        if (method_exists($h, 'getFieldBody')) return $h->getFieldBody();
                        if (method_exists($h, 'getBodyAsString')) return $h->getBodyAsString();
                        try { return (string) $h; } catch (\Throwable $__e) {}
                    }
                }
            }

            // Fallback: parse raw string
            if (method_exists($message, 'toString')) {
                $raw = $message->toString();
            } elseif (method_exists($message, '__toString')) {
                $raw = (string) $message;
            } else {
                $raw = null;
            }

            if (!empty($raw) && preg_match('/^Message-(?:Id|ID):\s*(.+)$/mi', $raw, $m)) {
                return trim($m[1]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * Extract headers as an associative array where possible
     */
    protected function extractHeaders($message)
    {
        $result = [];
        try {
            if (method_exists($message, 'getHeaderLine')) {
                // If we can list common headers
                $names = ['From','To','Cc','Bcc','Subject','Message-Id','Message-ID','Content-Type','X-Email-Log-Id'];
                foreach ($names as $n) {
                    $v = $message->getHeaderLine($n);
                    if (!empty($v)) $result[$n] = $v;
                }
                if (!empty($result)) return $result;
            }

            if (method_exists($message, 'getHeaders')) {
                $headers = $message->getHeaders();
                if (method_exists($headers, 'all')) {
                    $all = $headers->all();
                    foreach ($all as $h) {
                        try {
                            $name = method_exists($h, 'getName') ? $h->getName() : null;
                            $value = null;
                            if (method_exists($h, 'getBodyAsString')) $value = $h->getBodyAsString();
                            elseif (method_exists($h, 'getFieldBody')) $value = $h->getFieldBody();
                            elseif (method_exists($h, '__toString')) $value = (string)$h;
                            if ($name) $result[$name] = $value;
                        } catch (\Throwable $__e) {
                            // ignore single header
                        }
                    }
                    if (!empty($result)) return $result;
                }
            }

            // Last resort: raw string parse
            if (method_exists($message, 'toString')) {
                $raw = $message->toString();
            } elseif (method_exists($message, '__toString')) {
                $raw = (string) $message;
            } else {
                $raw = null;
            }

            if (!empty($raw)) {
                $lines = preg_split('/\r\n|\n|\r/', $raw);
                foreach ($lines as $line) {
                    if (strpos($line, ':') !== false) {
                        [$k,$v] = explode(':', $line, 2);
                        $k = trim($k); $v = trim($v);
                        if ($k && $v) $result[$k] = $v;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $result;
    }
}
