# phpcas

<p align="center">
    <a href="https://packagist.org/packages/iwannamaybe/phpcas">
        <img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status">
    </a>
    <a href="https://packagist.org/packages/iwannamaybe/phpcas">
        <img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License">
    </a>
</p>

## About Laravel
PhpCas client for the Laravel Framework 5.5.

## Author
Yanghaiquan

## Usage
create auth middleware then set the handle method like this:

    /**
     * Handle an incoming request.
     *
     * @param  Request $request
     * @param  Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
	    PhpCas::handLogoutRequest();
	    return PhpCas::checkAuthentication($request,$next,function ($userId){
		    $user = User::firstOrNew(['mobile' => $userId],['password' => bcrypt(123456)]);
		    return Auth::loginUsingId($user->id);
	    });
    }


## License
The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).