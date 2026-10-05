<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// trip.{id} é público (sem autorização): só carrega IDs de assentos e trechos.

Broadcast::channel('App.Models.User.{id}', fn (User $user, string $id): bool => $user->id === $id);
