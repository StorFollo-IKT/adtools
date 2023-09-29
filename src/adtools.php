<?php
namespace datagutten\adtools;
use datagutten\adtools\exceptions\LdapException;
use datagutten\adtools\exceptions\MultipleHitsException;
use datagutten\adtools\exceptions\NoHitsException;
use Exception;
use InvalidArgumentException;

class adtools
{
    /**
     * @var $ad resource LDAP link identifier
     */
    public $ad;
    /**
     * @var $error string Error message
     */
	public $error;
    /**
     * @var $config array Configuration loaded from config file
     */
    public $config=array();
    public $locale_path;
    /**
     * adtools constructor.
     * @param string $domain domain key from config file to connect to
     * @throws Exception
     */
    function __construct($domain=null)
	{
		if(!empty($domain))
			$this->connect($domain);
		set_locale('nb_NO.utf8', 'adtools');
    }

    /**
     * Connect and bind using config file
     * @param $domain_key
     * @throws Exception
     */
    function connect($domain_key)
	{
		$domains = require 'domains.php';
		if(!isset($domains[$domain_key]))
			throw new InvalidArgumentException(sprintf(_('Domain key %s not found in config file'),$domain_key));

		$this->config=$domains[$domain_key];

		if(!isset($this->config['dc']) && !isset($this->config['domain']))
			throw new InvalidArgumentException(_('DC and/or domain must be specified in config file'));
		elseif(!isset($this->config['dc']))
			$this->config['dc']=$this->config['domain'];
		elseif(!isset($this->config['domain']))
			$this->config['domain']=$this->config['dc'];
		//Use default values if options not set
		if(!isset($this->config['protocol']))
			$this->config['protocol']=null;
		if(!isset($this->config['port']))
			$this->config['port']=null;

		if(isset($this->config['username']) && isset($this->config['password']))
			$this->connect_and_bind($this->config['username'],$this->config['password'],$this->config['dc'], $this->config['protocol'],$this->config['port']);
	}

    /**
     * Connect and bind using specified credentials
     * @param string $username
     * @param string $password
     * @param string $dc
     * @param string $protocol Set to ldap, ldaps or leave blank to use config file
     * @param int $port
     * @throws Exception
     */
    function connect_and_bind($username, $password, $dc=null, $protocol=null, $port=null)
	{
		//http://php.net/manual/en/function.ldap-bind.php#73718
		if(empty($username) || empty($password))
            throw new InvalidArgumentException(_('Username and/or password are not specified'));
		if(preg_match('/[^a-zA-Z@\.\,\-0-9\=]/',$username) || preg_match('/[^a-zA-Z0-9\x20!@#$%^&*()+\-]/',$password))
            throw new InvalidArgumentException(_('Invalid characters in username or password'));
		if(!empty($port) && !is_numeric($port))
			throw new InvalidArgumentException('Port number must be numeric');


		//https://github.com/adldap/adLDAP/wiki/LDAP-over-SSL
		//http://serverfault.com/questions/136888/ssl-certifcate-request-s2003-dc-ca-dns-name-not-avaiable/705724#705724

		if(empty($protocol))
        {
            if(!empty($this->config['protocol'])) //Use value from config file
                $protocol = $this->config['protocol'];
            else
                $protocol = 'ldap';
        }

        if(!is_string($protocol) || ($protocol!='ldap' && $protocol!='ldaps'))
            throw new InvalidArgumentException('Invalid protocol specified');

        //PHP/OpenLDAP will default to port 389 even if ldaps is specified
        if($protocol=='ldaps' && (empty($port) || !is_numeric($port)))
            $port=636;

		if(empty($dc))
		{
			if(isset($this->config['dc']))
				$dc=$this->config['dc'];
			else
				throw new InvalidArgumentException('DC not specified and not set in config');
		}

		$url=sprintf('%s://%s',$protocol,$dc);
		if(!empty($port))
			$url.=':'.$port;

		$this->ad=ldap_connect($url);
		if($this->ad===false)
            throw new Exception(_('Unable to connect'));

		ldap_set_option($this->ad, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->ad, LDAP_OPT_NETWORK_TIMEOUT, 1);
		if (!ldap_set_option($this->ad, LDAP_OPT_REFERRALS, 0))
            throw new Exception('Failed to set opt referrals to 0');

		if(ldap_bind($this->ad,$username,$password)===false)
		{
			//http://php.net/manual/en/function.ldap-bind.php#103034
			if(ldap_errno($this->ad)===49)
                throw new Exception(_('Invalid user name or password'));
			else
			    throw new LdapException($this->ad);
		}
	}

    /**
     * Do a ldap query and get results
     * @param $query
     * @param array $options {
     * Query options
     *      @type bool $single_result Assume there should be only one result, throw exception if multiple is found
     *      @type bool $subtree Search sub tree
     *      @type array $attributes Attributes to be returned
     *      @type string $base_dn Base DN
     * }
     * @return array|string Array with data. If there is one result and one field the string value is returned
     * @throws InvalidArgumentException
     * @throws LdapException Error from LDAP
     * @throws NoHitsException No hits found
     * @throws MultipleHitsException Multiple hits when single was expected
     */
    function ldap_query($query, $options=array('single_result' => true, 'subtree' => true, 'attributes' => array('dn')))
    {
        $options_default = array('single_result' => true, 'subtree' => true, 'attributes' => array('dn'));
        $options = array_merge($options_default, $options);

        if(!is_resource($this->ad) && !is_object($this->ad))
            throw new InvalidArgumentException('Not connected to AD');
        if(empty($options['base_dn']))
        {
            if(!empty($this->config['dn']))
                $options['base_dn']=$this->config['dn'];
            else
                throw new InvalidArgumentException('Base DN empty and not set in config');
        }
        if(!is_array($options['attributes']))
            throw new InvalidArgumentException('attributes must be array');

        if($options['subtree'])
            $result=ldap_search($this->ad,$options['base_dn'],$query,$options['attributes']);
        else
            $result=ldap_list($this->ad,$options['base_dn'],$query,$options['attributes']);

        if($result===false)
            throw new LdapException($this->ad);

        $entries=ldap_get_entries($this->ad,$result);

        if($entries['count']==0)
        {
            throw new NoHitsException($query);
        }
        if($options['single_result']===true)
        {
            if($entries['count']>1)
                throw new MultipleHitsException($query);

            if(count($options['attributes'])==1)
            {
                $field=strtolower($options['attributes'][0]);
                if(!empty($entries[0][$field]))
                {
                    if(is_array($entries[0][$field])) //Field is array
                        return $entries[0][$field][0];
                    else
                        return $entries[0][$field];
                }
                else
                {
                    throw new InvalidArgumentException(sprintf(_('Field %s is empty'),$field));
                }
            }
            else
                return $entries[0];
        }
        else
            return $entries;
    }

    /**
     * Find an object in AD
     * @param $name
     * @param bool $base_dn
     * @param string $type
     * @param bool $fields
     * @return array
     * @throws Exception
     */
	function find_object($name,$base_dn=false,$type='user',$fields=false)
	{
		if($base_dn===false)
			$base_dn=$this->config['dn'];

		if($fields!==false && !is_array($fields))
			throw new InvalidArgumentException("Fields must be array or false");

		$options = array(
		    'base_dn'=>$base_dn,
            'single_result'=>true
        );

		if(!empty($fields))
		    $options['attributes'] = $fields;

		if($type=='user')
        {
            if(empty($options['attributes']))
                $options['attributes'] = array('sAMAccountName');

            return $this->ldap_query("(&(displayName=$name)(objectClass=user))", $options);
        }
		elseif($type=='upn')
        {
            if(empty($options['attributes']))
                $options['attributes'] = array('userPrincipalName');
            return $this->ldap_query("(&(userPrincipalName=$name)(objectClass=user))", $options);
        }
		elseif($type=='username')
        {
            if(empty($options['attributes']))
                $options['attributes'] = array('sAMAccountName');
            return $this->ldap_query("(&(sAMAccountName=$name)(objectClass=user))", $options);
        }
		elseif($type=='computer')
        {
            if(empty($options['attributes']))
                $options['attributes'] = array('name');
            return $this->ldap_query("(&(name=$name)(objectClass=computer))",$options);
        }
		else
			throw new InvalidArgumentException('Invalid type');
	}

    /**
     * Create a HTML login form
     * @return string HTML code
     * @deprecated Should be replaced with something else
     */
	function login_form()
	{
		return '<form id="form1" name="form1" method="post">
  <p>
    <label for="username">'._('Username').':</label>
    <input type="text" name="username" id="username">
  </p>
  <p>
    <label for="password">'._('Password').':</label>
    <input type="password" name="password" id="password">
  </p>
  <p>
    <input type="submit" name="submit" id="submit" value="Submit">
  </p>
</form>';
	}

    /**
     * Move an object to another OU
     * @param string $dn Object DN
     * @param string $newparent New parent OU
     * @return string New object DN
     * @throws LdapException
     */
	function move($dn,$newparent)
	{
		$cn=preg_replace('/(CN=.+?),[A-Z]{2}.+/','$1',$dn);
		$result = ldap_rename($this->ad,$dn,$cn,$newparent,true);
		if($result===false)
		    throw new LdapException($this->ad);
		return sprintf('%s,%s', $cn, $newparent);
	}

    /**
     * Reset password for user
     * @param string $dn User DN
     * @param string $password Password
     * @param bool $must_change_password
     * @throws InvalidArgumentException
     * @throws LdapException
     */
	function change_password($dn,$password,$must_change_password=false)
	{
		if(empty($dn) || empty($password))
			throw new InvalidArgumentException('DN or password is empty or not specified');

		$fields=array('unicodePwd'=> adtools_utils::pwd_encryption($password));
		if($must_change_password!==false)
			$fields['pwdLastSet']=0;
		$result=ldap_mod_replace($this->ad,$dn,$fields);
		if($result===false)
		    throw new LdapException($this->ad);
	}

    function __destruct()
	{
		if(is_object($this->ad))
			ldap_unbind($this->ad);
	}
}
