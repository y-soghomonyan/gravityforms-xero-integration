<?php
class StorageClass
{
    public $_xero_token = array();

    function __construct() {
        if( empty($this->_xero_token) ){
            $this->init_session();
        }
    }

    public function init_session(){
        $this->_xero_token = get_option('xero_token');
    }

    public function getSession() {
        return $this->_xero_token['oauth2'] ?? array();
    }

    public function startSession($token, $secret, $expires = null)
    {
        if( empty($this->_xero_token) ){
            $this->init_session();
        }
    }

    public function setToken($token, $expires, $tenantId, $refreshToken, $idToken)
    {
        $xero_token = array(
            'oauth2' => array(
                'token' => $token,
                'expires' => $expires,
                'tenant_id' => $tenantId,
                'refresh_token' => $refreshToken,
                'id_token' => $idToken
            )
        );
        update_option('xero_token', $xero_token);
        $this->_xero_token = $xero_token;
    }

    public function getToken()
    {
        //If it doesn't exist or is expired, return null
        if (empty($this->getSession())
            || ($this->_xero_token['oauth2']['expires'] !== null
                && $this->_xero_token['oauth2']['expires'] <= time())
        ) {
            return null;
        }
        return $this->getSession();
    }

    public function getAccessToken()
    {
        return $this->_xero_token['oauth2']['token'];
    }

    public function getRefreshToken()
    {
        return $this->_xero_token['oauth2']['refresh_token'] ?? '';
    }

    public function getExpires()
    {
        return $this->_xero_token['oauth2']['expires'];
    }

    public function getXeroTenantId()
    {
        return $this->_xero_token['oauth2']['tenant_id'];
    }

    public function getIdToken()
    {
        return $this->_xero_token['oauth2']['id_token'];
    }

    public function getHasExpired()
    {
        if (!empty($this->getSession()))
        {
            if(time() > $this->getExpires())
            {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }
}
?>