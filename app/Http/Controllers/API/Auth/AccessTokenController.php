<?php

namespace App\Http\Controllers\API\Auth;

use App\Transformers\UserTransformer;
use App\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use \Laravel\Passport\Http\Controllers\AccessTokenController as ATC;

class AccessTokenController extends ATC
{
    public function issueToken(ServerRequestInterface $request)
    {

        $requestData = $request->getParsedBody();
        try {
            //get username (default is :email)
            $username = isset($requestData['username']) ? $requestData['username'] : $requestData['email'];

            //get user
            //change to 'email' if you want

            $user = User::where('email', '=', $username)->first();

            if ((bool) $user) {
                if ($user->type == config('constants.user_types.partner')) {
                    $tokens = $user->tokens;
                    foreach ($tokens as $token) {
                        $token->revoke();
                    }
                }
            }

            //generate token
            $tokenResponse = parent::issueToken($request);

            //convert response to json string
            $content = $tokenResponse->getContent();

            //convert json to array
            $data = json_decode($content, true);
            if(isset($data["error"])) {
                throw new OAuthServerException('The user credentials were incorrect.', 6, 'invalid_credentials', 401);
            }



            return response()->json([
                'success' => true,
                'user' => fractal($user, new UserTransformer()),
                'tokens' => $data
            ]);
        }
        catch (ModelNotFoundException $e) { // email notfound
            return response()->json([
                "success" => false,
                "message" => "user not found"
            ], 500);
        }
        catch (OAuthServerException $e) { //password not correct..token not granted
            //return error message
            return response()->json([
                "success" => false,
                "message" => "invalid credentials"
            ], 422);
        }
        catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "internal server error : - ".$e->getMessage()
            ], 500);
        }
    }
}
