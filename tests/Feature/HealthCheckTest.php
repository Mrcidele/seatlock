<?php

declare(strict_types=1);

it('responds on the health check endpoint', function (): void {
    $this->get('/up')->assertOk();
});
