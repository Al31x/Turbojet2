<?php

namespace App\Controllers;

use Silex\Application;
use App\Providers\UserProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class AuthController {
    protected $app;

    public function __construct(Application $app) {
        $this->app = $app;
    }

    /**
    * Authenticate the user based on a user/password pair.
    * Will generate a new access token if the user is allowed to access the application.
    *
    * @param Request $request. Expects to receive in the request:
    * - string username The username.
    * - string password The plain password.
    *
    * @return JsonResponse An HTTP response containing either :
    * - A 200 code with the generated token
    * - A 401 code with the error message if the user can't be authentcated
    */
    public function login(Request $request) {
        $username = $request->request->get('username');
        $plainPassword = $request->request->get('password');

        $userProvider = new UserProvider($this->app['db']);

        try {
            // Try finding the user through his username and get the proper encoder
            $user = $userProvider->loadUserByUsername($username);
            $encoder = $this->app['security.encoder_factory']->getEncoder($user);

            // Check if the plain password is valid and it's value is equal to the encoded password
            if(!$encoder->isPasswordValid($user->getPassword() , $plainPassword, $user->getSalt())) {
                // Incorrect password
                $errorMsg = $this->app['debug'] === true ? 'Incorrect password' : 'Bad credentials, please try again';
                return $this->app->json(['message' => $errorMsg], 401);
            }
        } catch (UsernameNotFoundException $e) {
            // Incorrect username
            $errorMsg = $this->app['debug'] === true ? 'Incorrect username' : 'Bad credentials, please try again';
            return $this->app->json(['message' => $errorMsg], 401);
        }

        // Credentials are valids, we can create the token and returns it to the user
        $userProvider->deleteUserTokens($username);
        $token = $userProvider->generateToken($username);

        return $this->app->json(['token' => $token]);
    }

    /**
    * Encode the given password based on user's encoder
    *
    * @param Request $request. Expects to receive:
    * - string username The username
    * - string password The plain password
    *
    * @return JsonResponse An HTTP response containing either a 200 code with the encoded password
    * or a 401 code with the error message if the username is not found
    */
    public function encode(Request $request) {
        $username = $request->request->get('username');
        $plainPassword = $request->request->get('password');

        $userProvider = new UserProvider($this->app['db']);

        try {
            // Try finding the user through his username and get the proper encoder
            $user = $userProvider->loadUserByUsername($username);
            $encoder = $this->app['security.encoder_factory']->getEncoder($user);

            // Encode the password
            $encodedPassword = $encoder->encodePassword($plainPassword, $user->getSalt());

        } catch (UsernameNotFoundException $e) {
            // Incorrect username
            return $this->app->json(['message' => 'Username not found'], 401);
        }

        // User found, we can retuns an encoded password
        return $this->app->json(['encodedPassword' => $encodedPassword]);
    }
}
