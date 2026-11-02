<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        version: "1.0.0",
        title: "API Gestion Matériel",
        description: "Documentation de l'API pour l'application de gestion de matériel"
    ),
    servers: [
        new OA\Server(url: "/")
    ]
)]
class OpenApiDoc {}
