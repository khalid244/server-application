<?php

namespace App\Helpers;

use App\Exceptions\Entities\AuthorizationException;
use Cache;
use Config;
use GuzzleHttp\Client;
use Request;
use Throwable;

/**
 * Class RecaptchaHelper
 * @package App\Helpers
 */
class RecaptchaHelper
{
    /**
     * @var string $user
     */
    private $user;
    /**
     * @var bool $solved
     */
    private $solved = false;

    /**
     * Increment amount of login tries for ip and login
     */
    public function incrementCaptchaAmounts(): void
    {
        if (!$this->captchaEnabled()) {
            return;
        }

        $cacheKey = $this->getCaptchaCacheKey();

        if (!Cache::has($cacheKey)) {
            Cache::put($cacheKey, 1, config('recaptcha.ttl'));
        } else {
            Cache::increment($cacheKey);
        }
    }

    /**
     * Shows captcha status from config
     *
     * @return bool
     */
    private function captchaEnabled(): bool
    {
        return config('recaptcha.enabled');
    }

    /**
     * Returns unique for ip and user login key
     * @return string
     */
    private function getCaptchaCacheKey(): string
    {
        $ip = Request::ip();
        $email = $this->user;
        return "AUTH_RECAPTCHA_LIMITER_{$ip}_{$email}_ATTEMPTS";
    }

    /**
     * Forget about tries of user to login
     */
    public function clearCaptchaAmounts(): void
    {
        if (!$this->captchaEnabled()) {
            return;
        }

        Cache::forget($this->getCaptchaCacheKey());
    }

    /**
     * @param array $credentials
     * @throws AuthorizationException
     */
    public function check(array $credentials): void
    {
        if ($this->isBanned()) {
            $this->incrementBanAmounts();
            throw new AuthorizationException(AuthorizationException::ERROR_TYPE_BANNED);
        }

        $this->user = $credentials['email'];

        if (isset($credentials['recaptcha'])) {
            $this->solve($credentials['recaptcha']);
        }

        if ($this->needsCaptcha()) {
            $this->incrementBanAmounts();
            throw new AuthorizationException(AuthorizationException::ERROR_TYPE_CAPTCHA,
                ['site_key' => config('recaptcha.site_key')]);
        }
    }

    /**
     * Tests if user on ban list
     *
     * @return bool
     */
    private function isBanned(): bool
    {
        if (!$this->banEnabled()) {
            return false;
        }

        $cacheKey = $this->getBanCacheKey();

        $banData = Cache::get($cacheKey, null);

        if (is_null($banData)) {
            return false;
        }

        if ($banData['amounts'] < config('recaptcha.ban_attempts')) {
            return false;
        }

        if ($banData['time'] + config('recaptcha.rate_limiter_ttl') < time()) {
            Cache::forget($cacheKey);
            return false;
        }

        return true;
    }

    /**
     * Shows ban limiter status from config
     *
     * @return bool
     */
    private function banEnabled(): bool
    {
        return $this->captchaEnabled() && config('recaptcha.rate_limiter_enabled');
    }

    /**
     * Returns unique for ip key
     *
     * @return string
     */
    private function getBanCacheKey(): string
    {
        $ip = Request::ip();
        return "AUTH_RATE_LIMITER_{$ip}";
    }

    /**
     * Increment amount of tries for ip
     */
    private function incrementBanAmounts(): void
    {
        if (!$this->banEnabled()) {
            return;
        }

        $cacheKey = $this->getBanCacheKey();

        $banData = Cache::get($cacheKey);

        if (is_null($banData)) {
            $banData = ['amounts' => 1, 'time' => time()];
        } else {
            $banData['amounts']++;
        }

        Cache::put($cacheKey, $banData, config('recaptcha.rate_limiter_ttl'));
    }

    /**
     * Sends request to google with captcha token for verify
     *
     * @param string $captchaToken
     */
    private function solve(string $captchaToken = ""): void
    {
        if (!$this->captchaEnabled()) {
            return;
        }

        if ($this->solved) {
            return;
        }

        $response = (new Client())->post(config('recaptcha.google_url'), [
            'form_params' => [
                'secret' => config('recaptcha.secret_key'),
                'response' => $captchaToken,
            ],
        ]);

        if ($response->getStatusCode() != 200) {
            return;
        }

        $response = $response->getBody();

        if (empty($response)) {
            return;
        }

        try {
            $data = json_decode($response, true);
        } catch (Throwable $throwable) {
            return;
        }

        if (isset($data['success']) && $data['success'] === true) {
            $this->solved = true;
        }
    }

    /**
     * Tests if we need to show captcha to user
     *
     * @return bool
     */
    private function needsCaptcha(): bool
    {
        if (!$this->captchaEnabled()) {
            return false;
        }

        if ($this->solved) {
            return false;
        }

        $cacheKey = $this->getCaptchaCacheKey();

        $attempts = Cache::get($cacheKey, null);

        if (is_null($attempts)) {
            return false;
        }

        if ($attempts <= config('recaptcha.failed_attempts')) {
            return false;
        }

        return true;
    }
}
