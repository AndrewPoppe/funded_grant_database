<?php

namespace YaleREDCap\FundedGrantDatabase;

/**
 * Use CAS Authentication in EM
 * 
 * @author Andrew Poppe
 */
class CasAuthenticator
{

    public $cas_host;
    public $cas_context;
    public $cas_port;
    public $cas_server_ca_cert_path;
    public $server_force_https;
    public $service_base_url;

    public function __construct(array $settings)
    {
        $this->cas_host = $settings["cas_host"];
        $this->cas_context = $settings["cas_context"];
        $this->cas_port = $settings["cas_port"];
        $this->cas_server_ca_cert_path = $settings["cas_server_ca_cert_path"];
        $this->server_force_https = $settings["server_force_https"];
        $this->service_base_url = $settings["service_base_url"];
    }

    /**
     * Initiate CAS authentication
     * 
     * @return string|boolean username of authenticated user (false if not authenticated)
     */
    public function authenticate()
    {

        require_once __DIR__ . '/../vendor/jasig/phpcas/CAS.php';

        // Enable https fix
        if ($this->server_force_https == 1) {
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
            $_SERVER['HTTP_X_FORWARDED_PORT'] = 443;
            $_SERVER['HTTPS'] = 'on';
            $_SERVER['SERVER_PORT'] = 443;
        }

        // Initialize phpCAS
        \phpCAS::client(CAS_VERSION_2_0, $this->cas_host, $this->cas_port, $this->cas_context, $this->service_base_url);

        // Set the CA certificate that is the issuer of the cert
        // on the CAS server
        \phpCAS::setCasServerCACert($this->cas_server_ca_cert_path);

        // force CAS authentication
        \phpCAS::forceAuthentication();

        // get authenticated username
        return \phpCAS::isAuthenticated() ? \phpCAS::getUser() : FALSE;
    }
}
