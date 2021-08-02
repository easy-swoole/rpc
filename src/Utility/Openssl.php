<?php


namespace EasySwoole\Rpc\Utility;


class Openssl
{
    private $key;
    private $method;

    public function __construct($key, $method = 'DES-EDE3')
    {
        $this->key = $key;
        $this->method = $method;
    }

    public function encrypt(string $data): string
    {
        return openssl_encrypt($data, $this->method, $this->key);
    }

    public function decrypt(string $raw): string
    {
        return openssl_decrypt($raw, $this->method, $this->key);
    }
}