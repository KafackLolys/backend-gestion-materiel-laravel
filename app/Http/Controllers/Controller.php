<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="API Gestion Matériel",
 *     description="Documentation de l'API pour l'application de gestion de matériel"
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="Serveur courant"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer"
 * )
 */
abstract class Controller
{
    //
}
