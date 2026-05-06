<?php
/*
 * StudipAuthOpenID.php - Stud.IP authentication using OpenID Connect
 * Copyright (c) 2021  André Noack <noack@data-quest.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;

class StudipAuthOIDC extends StudipAuthSSO
{
    /**
     * @var OpenIDConnectClient
     */
    private $oidc = null;

    /**
     * @var string
     */
    public $provider_url;

    /**
     * @var string
     */
    public $client_id;

    /**
     * @var string
     */
    public $client_secret;

    public ?string $redirect_uri = null;

    public ?string $logout_redirect_uri = null;

    /**
     * @var string[]
     */
    public $scopes = ['openid', 'email', 'profile'];

    /**
     * Which attribute contains the actual username usable for Stud.IP?
     * Set this to "preferred_username" in your config if you're using Keycloak.
     */
    public string $username_attribute = 'preferred_username';

    public function __construct($config = [])
    {
        parent::__construct($config);

        if (!isset($this->redirect_uri)) {
            $this->redirect_uri = URLHelper::getURL($GLOBALS['ABSOLUTE_URI_STUDIP'] . 'index.php', ['sso' => $this->plugin_name, 'again' => 'yes'], true);
        }
    }

    /**
     * Returns the configured OpenID Connect client.
     */
    protected function getClient(): OpenIDConnectClient
    {
        if ($this->oidc === null) {
            $this->oidc = new OpenIDConnectClient($this->provider_url, $this->client_id, $this->client_secret);
            if (isset($this->ssl_options)) {
                foreach ($this->ssl_options as $option_key => $option_value) {
                    if (isset($option_value)) {
                        $this->oidc->{'set' . $option_key}($option_value);
                    }
                }
            }

            if (Config::get()->HTTP_PROXY) {
                $this->oidc->setHttpProxy(Config::get()->HTTP_PROXY);
            }

            $this->oidc->setRedirectURL($this->redirect_uri);
            $this->oidc->addScope($this->scopes);
        }

        return $this->oidc;
    }

    /**
     * Validate the username passed to the auth plugin.
     *
     * @param string $username
     *
     * @return  string  username openid attribute user_id@domain
     *
     * @throws OpenIDConnectClientException
     */
    public function verifyUsername($username)
    {
        $this->getClient()->authenticate();
        $this->userdata = (array) $this->getClient()->requestUserInfo();

        if (!isset($this->userdata[$this->username_attribute])) {
            return null;
        }

        $username = $this->userdata[$this->username_attribute];

        if (isset($this->domain)) {
            $username .= '@' . $this->domain;
        } 

        return $this->userdata['username'] = $username;
    }

    /**
     * Return the current username of the pending authentication request.
     */
    public function getUser()
    {
        return $this->getUserData('username');
    }

    /**
     * Get the user domains to assign to the current user (if any).
     *
     * @return array    array of user domain names
     */
    public function getUserDomains()
    {
        return $this->domain ? [$this->domain] : null;
    }

    /**
     * Callback that can be used in user_data_mapping array.
     *
     * @see https://openid.net/specs/openid-connect-basic-1_0.html#StandardClaims
     *
     * @param string  $key
     * @return  string  parameter value (null if not set)
     */
    public function getUserData($key)
    {
        return $this->userdata[$key];
    }

    public function logout(): void
    {
        $this->getClient()->signOut(
            $this->getKeptVariable('id_token') ?? '',
            $this->logout_redirect_uri
        );
    }

    public function getKeptVariables(): array
    {
        $variables = parent::getKeptVariables();

        if ($this->getClient()->getIdToken()) {
            $variables['id_token'] = $this->getClient()->getIdToken();
        }

        return $variables;
    }
}
