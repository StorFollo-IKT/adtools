<?php
namespace storfollo\adtools;
use InvalidArgumentException;
use storfollo\adtools\exceptions\MultipleHitsException;
use storfollo\adtools\exceptions\NoHitsException;
use Symfony\Component\Ldap;

class adtools
{
    /**
     * @var $config array Configuration loaded from config file
     */
    public $config=array();
    /**
     * @var Ldap\Ldap
     */
    public $ldap;

    /**
     * @var $base_dn string Default base DN for queries
     */
    public $base_dn;

    /**
     * adtools constructor.
     */
    function __construct()
	{
		set_locale('nb_NO.utf8', 'adtools');
    }

    /**
     * Connect and bind using an array of configuration parameters
     * @param array $config Configuration parameters
     * @return adtools
     */
    public static function connect_config(array $config): adtools
    {
        if (!isset($config['dc']))
            throw new InvalidArgumentException(_('DC must be specified in config file'));
        $config = array_merge(['protocol'=>'ldap', 'port'=>389], $config);

        $adtools = new static();
        $adtools->config = $config;

        if (isset($config['username']) && isset($config['password']))
            $adtools->connect_and_bind($config['username'], $config['password'], $config['dc'], $config['protocol'], $config['port']);

        if(!empty($config['dn']))
            $adtools->base_dn = $config['dn'];

        return $adtools;
    }

    /**
     * Connect and bind using specified credentials
     * @param string $username
     * @param string $password
     * @param string $dc
     * @param string $protocol Set to ldap, ldaps or leave blank to use config file
     * @param int|null $port
     */
    function connect_and_bind(string $username, string $password, string $dc, $protocol='ldap', int $port=null)
	{
		//http://php.net/manual/en/function.ldap-bind.php#73718
		if(empty($username) || empty($password))
            throw new InvalidArgumentException(_('Username and/or password are not specified'));
		if(preg_match('/[^a-zA-Z@\.\,\-0-9\=]/',$username) || preg_match('/[^a-zA-Z0-9\x20!@#$%^&*()+\-]/',$password))
            throw new InvalidArgumentException(_('Invalid characters in username or password'));

		//https://github.com/adldap/adLDAP/wiki/LDAP-over-SSL
		//http://serverfault.com/questions/136888/ssl-certifcate-request-s2003-dc-ca-dns-name-not-avaiable/705724#705724

        if(!is_string($protocol) || ($protocol!='ldap' && $protocol!='ldaps'))
            throw new InvalidArgumentException('Invalid protocol specified');

        //PHP/OpenLDAP will default to port 389 even if ldaps is specified
        if($protocol=='ldaps' && (empty($port) || !is_numeric($port)))
            $port=636;

		$url=sprintf('%s://%s',$protocol,$dc);
		if(!empty($port))
			$url.=':'.$port;

        $this->ldap = Ldap\Ldap::create('ext_ldap', ['connection_string' => $url]);
        $this->ldap->bind($username, $password);
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
     * @throws NoHitsException No hits found
     * @throws MultipleHitsException Multiple hits when single was expected
     */
    function ldap_query($query, $options=array('single_result' => true, 'subtree' => true, 'attributes' => array('dn')))
    {
        $options_default = array('single_result' => true, 'subtree' => true, 'attributes' => array('dn'));
        $options = array_merge($options_default, $options);

        if(empty($options['base_dn']))
        {
            if(!empty($this->base_dn))
                $options['base_dn']=$this->base_dn;
            else
                throw new InvalidArgumentException('Base DN empty');
        }
        if(!is_array($options['attributes']))
            throw new InvalidArgumentException('attributes must be array');

        if($options['subtree'])
            $scope = Ldap\Adapter\QueryInterface::SCOPE_SUB;
        else
            $scope = Ldap\Adapter\QueryInterface::SCOPE_ONE;

        $result = $this->ldap->query($options['base_dn'], $query, ['scope'=>$scope])->execute();

        if($result->count()==0)
        {
            throw new NoHitsException($query);
        }
        if($options['single_result']===true)
        {
            if($result->count()>1)
                throw new MultipleHitsException($query);
            $entries = $result->toArray();

            if(count($options['attributes'])==1)
            {
                $entry = $entries[0];
                if($options['attributes'][0]=='dn')
                    return $entries[0]->getDn();

                if($entry->hasAttribute($options['attributes'][0]))
                {
                    $attribute_data = $entry->getAttribute($options['attributes'][0]);
                    if(is_array($attribute_data)) //Field is array
                        return $attribute_data[0];
                    else
                        return $attribute_data;
                }
                else
                {
                    throw new InvalidArgumentException('Entry has no attribute named ' . $options['attributes'][0]);
                }
            }
            else
                return $entries[0];
        }
        else
            return $result->toArray();
    }

    /**
     * Find an single object in AD
     * @param string $name
     * @param string $base_dn Base DN
     * @param string $type What to find
     * @return Computer|User
     * @throws NoHitsException No hits for the query
     * @throws MultipleHitsException Multiple hits for the query
     */
	function find_object(string $name, string $base_dn, $type='user')
	{
		if($type=='user' || $type == 'username')
            return User::from_attribute($this->ldap, $base_dn, 'sAMAccountName', $name);
		elseif($type=='upn')
            return User::from_attribute($this->ldap, $base_dn, 'userPrincipalName', $name);
		elseif($type=='computer')
            return Computer::from_attribute($this->ldap, $base_dn, 'name', $name);
		else
			throw new InvalidArgumentException('Invalid type');
	}

    /**
     * Get user from DN
     * @param $dn
     * @return User
     */
	public function user($dn): User
    {
        return User::from_dn($this->ldap, $dn);
    }

    /**
     * Get group from DN
     * @param $dn
     * @return Group
     */
	public function group($dn): Group
    {
        return Group::from_dn($this->ldap, $dn);
    }
}
