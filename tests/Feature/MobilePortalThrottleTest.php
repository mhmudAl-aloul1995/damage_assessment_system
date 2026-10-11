<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

it('keeps read traffic separate from the submission throttle and still limits writes', function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->withToken($user->createToken('throttle-fixture', ['mobile:read', 'mobile:submit'])->plainTextToken);
    for ($request = 0; $request < 12; $request++) {
        $this->getJson('/api/v1/me')->assertOk();
    }
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson('/api/v1/forms/borrower-survey/submissions', [])->assertUnprocessable();
    }
    $this->postJson('/api/v1/forms/borrower-survey/submissions', [])->assertTooManyRequests();
    $this->postJson('/api/v1/forms/borrower-survey/attachments', [])->assertUnprocessable();
});
