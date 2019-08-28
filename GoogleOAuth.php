<?php

namespace Utils;

use Google_Client;
use Google_Service_Docs;

class GoogleOAuth extends Google_Client
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;
    protected $scope;
    protected $tokenFile;
    protected $config;

    public function __construct()
    {
        $this->config = require(__DIR__.'../etc/app-conf.php');
        $this->tokenFile = __DIR__.'../etc/googleOAuthToken.txt';
    }

    /**
     * @return Google_Client
     */
    public function getClient()
    {
        if ($this->client) {
            return $this->client;
        }

        $this->clientId = $this->config['googleAPIclientId'];
        $this->clientSecret = $this->config['googleAPIclientSecret'];
        $this->redirectUri = $this->config['googleAPIredirectUri'];
        $this->scope = [
            Google_Service_Docs::DOCUMENTS,
            Google_Service_Docs::DRIVE
        ];

        $client = new Google_Client();
        $client->setApplicationName('LawDoc');
        $client->setScopes($this->scope);
        $client->setIncludeGrantedScopes(true);
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);

        $client->setRedirectUri($this->redirectUri);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        $accessToken = $this->getAccessToken();

        if ($accessToken !== false) {
            $client->setAccessToken($accessToken);

            if ($client->isAccessTokenExpired()) {
                $accessTokenUpdated = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

                $this->saveAccessToken($accessTokenUpdated);
            }
        }

        return $this->client = $client;
    }

    /**
     * @param null $scope
     * @return mixed
     */
    public function createAuthUrl($scope = null)
    {
        return $this->getClient()->createAuthUrl();
    }

    /**
     * @param $code
     * @return mixed
     */
    public function fetchAccessToken($code)
    {
        return $this->getClient()->fetchAccessTokenWithAuthCode($code);
    }

    public function saveAccessToken($accessToken)
    {
        if (!file_exists(dirname($this->tokenFile))) {
            mkdir(dirname($this->tokenFile), 0700, true);
        }

        file_put_contents($this->tokenFile, json_encode($accessToken));
    }

    /**
     * @return bool|mixed
     */
    public function getAccessToken()
    {
        if (!is_file($this->tokenFile)) {
            return false;
        }

        return json_decode(file_get_contents($this->tokenFile), 1);
    }
}
