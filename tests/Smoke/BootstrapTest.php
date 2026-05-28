<?php

it('boots service providers without errors', function (): void {
    $this->artisan('about')
        ->assertSuccessful();
});
