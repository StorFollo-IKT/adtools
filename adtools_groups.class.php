<?php
require_once 'adtools.class.php';
class adtools_groups extends adtools
{
	function __construct($domain)
	{
		parent::__construct($domain);
	}
	function create_group($object_name,$dn)
	{
		$addgroup_ad['cn']="$object_name";
		$addgroup_ad['objectClass'][0]="top";
		$addgroup_ad['objectClass'][1]="group";
		$addgroup_ad['groupType']=0x80000002; //Security gorup
		//$addgroup_ad['member']=$members;
		$addgroup_ad["sAMAccountName"]=$object_name;

		ldap_add($this->ad,$dn,$addgroup_ad);
		
		if(ldap_error($this->ad) == "Success")
		  return true;
		else
		  return false;
	}
	function member_add($user_dn,$group_dn)
	{
		if(ldap_mod_add($this->ad,$group_dn,array('member'=>$user_dn))===false)
			throw new Exception("Error adding $user_dn to $group_dn");
		else
			return true;
	}
	function member_del($user_dn,$group_dn) //Set $user_dn to empty array to remove all members from the group
	{
		return ldap_mod_del($this->ad,$group_dn,array('member'=>$user_dn));
	}
}