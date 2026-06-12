<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Contracts;

/**
 * Reads messages from a source until told to stop.
 *
 * Used by long-running workers (Fargate supervisord process) to consume
 * from SQS / EventBridge / SNS.
 */
interface Consumer
{
    /**
     * Block and consume messages. The callable is invoked for each
     * message; returning true acknowledges the message, false / throwing
     * leaves it for redelivery (or routes to DLQ after the retry budget).
     *
     * @param  callable(Envelope): void  $handler
     */
    public function consume(callable $handler): void;

    /**
     * Signal the consumer to stop after the current message.
     * Used by SIGTERM / SIGINT handlers.
     */
    public function stop(): void;
}
