<?php

$useSSL = true;

include(dirname(__FILE__).'/config/config.inc.php');
include(dirname(__FILE__).'/header.php');

$errors = array();

$smarty->assign('contacts', Contact::getContacts(intval($cookie->id_lang)));

if ($cookie->isLogged())
{
	$customer = new Customer(intval($cookie->id_customer));
	if (!Validate::isLoadedObject($customer))
		die(Tools::displayError('customer not found'));
	$products = array();
	$orders = array();
	$getOrders = Db::getInstance()->ExecuteS('
		SELECT id_order 
		FROM '._DB_PREFIX_.'orders 
		WHERE id_customer = '.(int)$customer->id.' AND valid = 1 ORDER BY date_add');
	foreach ($getOrders as $row)
	{
		$order = new Order($row['id_order']);
		$date = explode(' ', $order->invoice_date);
		$orders[$row['id_order']] = Tools::displayDate($date[0], $cookie->id_lang);
		$tmp = $order->getProducts();
		foreach ($tmp as $key => $val)
			$products[$val['product_id']] = $val['product_name'];
	}
	
	$orderList = '';
	foreach ($orders as $key => $val)
		$orderList .= '<option value="'.$key.'" '.(intval(Tools::getValue('id_order')) == $key ? 'selected' : '').' >'.$key.' -- '.$val.'</option>';
	$orderedProductList = '';
	
	foreach ($products as $key => $val)
		$orderedProductList .= '<option value="'.$key.'" '.(intval(Tools::getValue('id_product')) == $key ? 'selected' : '').' >'.$val.'</option>';
	$smarty->assign('isLogged', 1);
	$smarty->assign('orderList', $orderList);
	$smarty->assign('orderedProductList', $orderedProductList);
}

if (Tools::isSubmit('submitMessage'))
{
	$message = Tools::htmlentitiesUTF8(Tools::getValue('message'));
	if (!($from = Tools::getValue('from')) OR !Validate::isEmail($from))
        $errors[] = Tools::displayError('invalid e-mail address');
    elseif (!($message = nl2br2($message)))
        $errors[] = Tools::displayError('message cannot be blank');
    elseif (!Validate::isMessage($message))
        $errors[] = Tools::displayError('invalid message');
    elseif (!($id_contact = intval(Tools::getValue('id_contact'))) OR !(Validate::isLoadedObject($contact = new Contact(intval($id_contact), intval($cookie->id_lang)))))
    	$errors[] = Tools::displayError('please select a subject in the list');
    else
    {
		if (intval($cookie->id_customer))
			$customer = new Customer(intval($cookie->id_customer));
		else
		{
			$customer = new Customer();
			$customer->getByEmail($from);
		}
		
		$contact = new Contact($id_contact, $cookie->id_lang);
		if (!empty($contact->email))
		{
			if (Mail::Send(intval($cookie->id_lang), 'contact', Mail::l('Message from contact form'), array('{email}' => $from, '{message}' => stripslashes($message)), $contact->email, $contact->name, $from, (intval($cookie->id_customer) ? $customer->firstname.' '.$customer->lastname : $from)))
				$smarty->assign('confirmation', 1);
			else
				$errors[] = Tools::displayError('an error occurred while sending message');
		}
		
		if ($contact->customer_service)
		{
			if ((
					$id_customer_thread = (int)Tools::getValue('id_customer_thread')
					AND (int)Db::getInstance()->getValue('
					SELECT cm.id_customer_thread FROM '._DB_PREFIX_.'customer_thread cm
					WHERE cm.id_customer_thread = '.(int)$id_customer_thread.' AND token = \''.pSQL(Tools::getValue('token')).'\'')
				) OR (
					$id_customer_thread = (int)Db::getInstance()->getValue('
					SELECT cm.id_customer_thread FROM '._DB_PREFIX_.'customer_thread cm
					WHERE cm.email = \''.pSQL(Tools::getValue('from')).'\'
					')
				))
			{
				$ct = new CustomerThread($id_customer_thread);
				$ct->status = 'open';
				$ct->id_lang = (int)$cookie->id_lang;
				$ct->id_contact = intval($id_contact);
				if ($id_order = (int)Tools::getValue('id_order'))
					$ct->id_order = $id_order;
				if ($id_product = (int)Tools::getValue('id_product'))
					$ct->id_product = $id_product;
				$ct->update();
			}
			else
			{
				$ct = new CustomerThread();
				if (isset($customer->id))
					$ct->id_customer = intval($customer->id);
				if ($id_order = (int)Tools::getValue('id_order'))
					$ct->id_order = $id_order;
				if ($id_product = (int)Tools::getValue('id_product'))
					$ct->id_product = $id_product;
				$ct->id_contact = intval($id_contact);
				$ct->id_lang = (int)$cookie->id_lang;
				$ct->email = Tools::getValue('from');
				$ct->status = 'open';
				$ct->token = Tools::passwdGen(12);
				$ct->add();
			}
			
			if ($ct->id)
			{
				$cm = new CustomerMessage();
				$cm->id_customer_thread = $ct->id;
				$cm->message = htmlentities($message, ENT_COMPAT, 'UTF-8');
				$cm->ip_address = ip2long($_SERVER['REMOTE_ADDR']);
				$cm->user_agent = $_SERVER['HTTP_USER_AGENT'];
				if ($cm->add())
					$smarty->assign('confirmation', 1);
				else
					$errors[] = Tools::displayError('an error occurred while sending message');
			}
			else
				$errors[] = Tools::displayError('an error occurred while sending message');
		}
		if (count($errors) > 1)
			array_unique($errors);
    }
}

$email = Tools::safeOutput(Tools::getValue('from', ((isset($cookie) AND isset($cookie->email) AND Validate::isEmail($cookie->email)) ? $cookie->email : '')));
$smarty->assign(array(
	'errors' => $errors,
	'email' => $email
));


if ($id_customer_thread = (int)Tools::getValue('id_customer_thread') AND $token = Tools::getValue('token'))
{
	$customerThread = Db::getInstance()->getRow('
	SELECT cm.* FROM '._DB_PREFIX_.'customer_thread cm
	WHERE cm.id_customer_thread = '.(int)$id_customer_thread.' AND token = \''.pSQL($token).'\'');
	$smarty->assign('customerThread', $customerThread);
}

$_POST = array_merge($_POST, $_GET);

$smarty->display(_PS_THEME_DIR_.'contact-form.tpl');
include(dirname(__FILE__).'/footer.php');

?>
