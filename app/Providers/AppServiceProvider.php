<?php

namespace Neocom\JWK\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Neocom\JWK\Contracts\Cache\KeyCache as KeyCacheContract;
use Neocom\JWK\Contracts\Helpers\KeyConverter as KeyConverterContract;
use Neocom\JWK\Contracts\Helpers\KeyEncryptor as KeyEncryptorContract;
use Neocom\JWK\Contracts\Helpers\KeyGenerator as KeyGeneratorContract;
use Neocom\JWK\Contracts\Repositories\EncryptionKeyRepository as EncryptionKeyRepositoryContract;
use Neocom\JWK\Contracts\Repositories\KeyRepository as KeyRepositoryContract;
use Neocom\JWK\Cache\KeyCache;
use Neocom\JWK\Contracts\Helpers\EncryptionKeyGenerator as EncryptionKeyGeneratorContract;
use Neocom\JWK\Helpers\EncryptionKeyGenerator;
use Neocom\JWK\Helpers\KeyConverter;
use Neocom\JWK\Helpers\KeyEncryptor;
use Neocom\JWK\Helpers\KeyGenerator;
use Neocom\JWK\Repositories\EncryptionKeyRepository;
use Neocom\JWK\Repositories\KeyRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register our model repositories
        $this->app->bind(KeyRepositoryContract::class, KeyRepository::class);
        $this->app->bind(EncryptionKeyRepositoryContract::class, EncryptionKeyRepository::class);

        // Register the cache handler
        $this->app->bind(KeyCacheContract::class, KeyCache::class);

        // Register our key generators
        $this->app->bind(EncryptionKeyGeneratorContract::class, EncryptionKeyGenerator::class);
        $this->app->bind(KeyGeneratorContract::class, KeyGenerator::class);

        // Register the key converter
        $this->app->bind(KeyConverterContract::class, KeyConverter::class);

        // Register the encryptor
        $this->app->bind(KeyEncryptorContract::class, KeyEncryptor::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Add some string macros
        Str::macro('removePrefix', function ($str, $prefix) {
            /** @var Str $this */
            if (static::startsWith($str, $prefix)) {
                return static::substr($str, static::length($prefix));
            }
            return $str;
        });

        // Add an array defaults macro
        Arr::macro('applyDefaults', function ($arr, $defaults, bool $stripMissingKeys = false) {
            /** @var Arr $this */
            return collect($defaults)
                ->merge($arr)
                ->when($stripMissingKeys)
                ->only(array_keys($defaults))
                ->all();
        });

        // Some request macros
        Request::macro('getParametersWithPrefix', function (string $prefix) {
            /** @var Request $this */
            return collect($this->all())
                ->filter(function ($value, $key) use ($prefix) {
                    return Str::startsWith($key, $prefix);
                })
                ->mapWithKeys(function ($value, $key) {
                    return [ Str::lower($key) => Str::lower($value) ];
                })
                ->mapWithKeys(function ($value, $key) use ($prefix) {
                    return [ Str::removePrefix($key, $prefix) => $value ];
                })
                ->toArray();
        });
        Request::macro('getKeyType', function (string $defaultType) {
            /** @var Request $this */
            $type = Str::lower($this->input('type'));

            // Check the default type and override it if needed
            if ($defaultType === 'all' && in_array($type, ['private', 'public'])) {
                return $type;
            }
            return $defaultType;
        });
    }
}
