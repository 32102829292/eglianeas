<?php

// The only file to adjust if your names differ.
return [
    // Model behind the {client} route parameter.
    'client_model' => App\Models\User::class,

    // How to pick only clients from that table. Set the column to null if the model holds clients only.
    'role_column' => 'role',
    'role_value' => 'client',

    // Relation on the client model that returns its ClientProfile.
    'profile_relation' => 'profile',
];