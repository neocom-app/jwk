<?php

namespace Neocom\JWK\Http\Controllers\V1\Keys;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Neocom\JWK\Contracts\Helpers\EncryptionKeyGenerator;
use Neocom\JWK\Contracts\Repositories\EncryptionKeyRepository;
use Neocom\JWK\Http\Controllers\Controller;

class EncryptionKeyController extends Controller
{
    /**
     * Register an encryption key for a client
     *
     * @param Request $request
     * @return Response
     */
    public function register(Request $request, EncryptionKeyRepository $repository, EncryptionKeyGenerator $generator)
    {
        // Get the secret from the request body
        $secret = $request->input('secret', '');

        // If there is no secret, throw an error
        if (! $secret) {
            return response()->json([
                'error' => 'No secret has been provided'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create the encryption key
        $keyData = $generator->generateEncryptionKey($secret);

        // Save the encryption key
        $result = $repository->createEncryptionKey($keyData['key'], $keyData['hash']);

        // If the key was saved successfully, return the data
        if ($result) {
            $response = response()->json([
                'data' => $keyData,
            ]);
        } else {
            $response = response()->json([
                'error' => 'Unable to generate the encryption key',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return $response;
    }
}
