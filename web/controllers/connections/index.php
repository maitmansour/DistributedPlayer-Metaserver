<?php

/*
 * This file is part of the CRUD Admin Generator project.
 *
 * Author: Jon Segador <jonseg@gmail.com>
 * Web: http://crud-admin-generator.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../src/app.php';

use Symfony\Component\Validator\Constraints as Assert;

$app->match('/connections/list', function (Symfony\Component\HttpFoundation\Request $request) use ($app) {  
    $start = 0;
    $vars = $request->query->all();
    $qsStart = (int)$vars["start"];
    $search = $vars["search"];
    $order = $vars["order"];
    $columns = $vars["columns"];
    $qsLength = (int)$vars["length"];    
    
    if($qsStart) {
        $start = $qsStart;
    }    
	
    $index = $start;   
    $rowsPerPage = $qsLength;
       
    $rows = array();
    
    $searchValue = $search['value'];
    $orderValue = $order[0];
    
    $orderClause = "";
    if($orderValue) {
        $orderClause = " ORDER BY ". $columns[(int)$orderValue['column']]['data'] . " " . $orderValue['dir'];
    }
    
    $table_columns = array(
		'id', 
		'ip', 
		'radio', 
		'tele', 
		'clients', 
		'last_con', 
		'is_connected', 

    );
    
    $table_columns_type = array(
		'int(11)', 
		'varchar(50)', 
		'varchar(50)', 
		'varchar(50)', 
		'int(11)', 
		'timestamp', 
		'tinyint(4)', 

    );    
    
    $whereClause = "";
    
    $i = 0;
    foreach($table_columns as $col){
        
        if ($i == 0) {
           $whereClause = " WHERE";
        }
        
        if ($i > 0) {
            $whereClause =  $whereClause . " OR"; 
        }
        
        $whereClause =  $whereClause . " " . $col . " LIKE '%". $searchValue ."%'";
        
        $i = $i + 1;
    }
    
    $recordsTotal = $app['db']->executeQuery("SELECT * FROM `connections`" . $whereClause . $orderClause)->rowCount();
    
    $find_sql = "SELECT * FROM `connections`". $whereClause . $orderClause . " LIMIT ". $index . "," . $rowsPerPage;
    $rows_sql = $app['db']->fetchAll($find_sql, array());

    foreach($rows_sql as $row_key => $row_sql){
        for($i = 0; $i < count($table_columns); $i++){

		if( $table_columns_type[$i] != "blob") {
				$rows[$row_key][$table_columns[$i]] = $row_sql[$table_columns[$i]];
		} else {				if( !$row_sql[$table_columns[$i]] ) {
						$rows[$row_key][$table_columns[$i]] = "0 Kb.";
				} else {
						$rows[$row_key][$table_columns[$i]] = " <a target='__blank' href='menu/download?id=" . $row_sql[$table_columns[0]];
						$rows[$row_key][$table_columns[$i]] .= "&fldname=" . $table_columns[$i];
						$rows[$row_key][$table_columns[$i]] .= "&idfld=" . $table_columns[0];
						$rows[$row_key][$table_columns[$i]] .= "'>";
						$rows[$row_key][$table_columns[$i]] .= number_format(strlen($row_sql[$table_columns[$i]]) / 1024, 2) . " Kb.";
						$rows[$row_key][$table_columns[$i]] .= "</a>";
				}
		}

        }
    }    
    
    $queryData = new queryData();
    $queryData->start = $start;
    $queryData->recordsTotal = $recordsTotal;
    $queryData->recordsFiltered = $recordsTotal;
    $queryData->data = $rows;
    
    return new Symfony\Component\HttpFoundation\Response(json_encode($queryData), 200);
});




/* Download blob img */
$app->match('/connections/download', function (Symfony\Component\HttpFoundation\Request $request) use ($app) { 
    
    // menu
    $rowid = $request->get('id');
    $idfldname = $request->get('idfld');
    $fieldname = $request->get('fldname');
    
    if( !$rowid || !$fieldname ) die("Invalid data");
    
    $find_sql = "SELECT " . $fieldname . " FROM " . connections . " WHERE ".$idfldname." = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($rowid));

    if(!$row_sql){
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );        
        return $app->redirect($app['url_generator']->generate('menu_list'));
    }

    header('Content-Description: File Transfer');
    header('Content-Type: image/jpeg');
    header("Content-length: ".strlen( $row_sql[$fieldname] ));
    header('Expires: 0');
    header('Cache-Control: public');
    header('Pragma: public');
    ob_clean();    
    echo $row_sql[$fieldname];
    exit();
   
    
});



$app->match('/connections', function () use ($app) {
    
	$table_columns = array(
		'id', 
		'ip', 
		'radio', 
		'tele', 
		'clients', 
		'last_con', 
		'is_connected', 

    );

    $primary_key = "id";	

    return $app['twig']->render('connections/list.html.twig', array(
    	"table_columns" => $table_columns,
        "primary_key" => $primary_key
    ));
        
})
->bind('connections_list');



$app->match('/connections/create', function () use ($app) {
    
    $initial_data = array(
		'ip' => '', 
		'radio' => '', 
		'tele' => '', 
		'clients' => '', 
		'last_con' => '', 
		'is_connected' => '', 

    );

    $form = $app['form.factory']->createBuilder('form', $initial_data);



	$form = $form->add('ip', 'text', array('required' => true));
	$form = $form->add('radio', 'text', array('required' => true));
	$form = $form->add('tele', 'text', array('required' => true));
	$form = $form->add('clients', 'text', array('required' => true));
	$form = $form->add('last_con', 'text', array('required' => true));
	$form = $form->add('is_connected', 'text', array('required' => true));


    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();

            $update_query = "INSERT INTO `connections` (`ip`, `radio`, `tele`, `clients`, `last_con`, `is_connected`) VALUES (?, ?, ?, ?, ?, ?)";
            $app['db']->executeUpdate($update_query, array($data['ip'], $data['radio'], $data['tele'], $data['clients'], $data['last_con'], $data['is_connected']));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'connections created!',
                )
            );
            return $app->redirect($app['url_generator']->generate('connections_list'));

        }
    }

    return $app['twig']->render('connections/create.html.twig', array(
        "form" => $form->createView()
    ));
        
})
->bind('connections_create');



$app->match('/connections/edit/{id}', function ($id) use ($app) {

    $find_sql = "SELECT * FROM `connections` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));

    if(!$row_sql){
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );        
        return $app->redirect($app['url_generator']->generate('connections_list'));
    }

    
    $initial_data = array(
		'ip' => $row_sql['ip'], 
		'radio' => $row_sql['radio'], 
		'tele' => $row_sql['tele'], 
		'clients' => $row_sql['clients'], 
		'last_con' => $row_sql['last_con'], 
		'is_connected' => $row_sql['is_connected'], 

    );


    $form = $app['form.factory']->createBuilder('form', $initial_data);


	$form = $form->add('ip', 'text', array('required' => true));
	$form = $form->add('radio', 'text', array('required' => true));
	$form = $form->add('tele', 'text', array('required' => true));
	$form = $form->add('clients', 'text', array('required' => true));
	$form = $form->add('last_con', 'text', array('required' => true));
	$form = $form->add('is_connected', 'text', array('required' => true));


    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();

            $update_query = "UPDATE `connections` SET `ip` = ?, `radio` = ?, `tele` = ?, `clients` = ?, `last_con` = ?, `is_connected` = ? WHERE `id` = ?";
            $app['db']->executeUpdate($update_query, array($data['ip'], $data['radio'], $data['tele'], $data['clients'], $data['last_con'], $data['is_connected'], $id));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'connections edited!',
                )
            );
            return $app->redirect($app['url_generator']->generate('connections_edit', array("id" => $id)));

        }
    }

    return $app['twig']->render('connections/edit.html.twig', array(
        "form" => $form->createView(),
        "id" => $id
    ));
        
})
->bind('connections_edit');



$app->match('/connections/delete/{id}', function ($id) use ($app) {

    $find_sql = "SELECT * FROM `connections` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));

    if($row_sql){
        $delete_query = "DELETE FROM `connections` WHERE `id` = ?";
        $app['db']->executeUpdate($delete_query, array($id));

        $app['session']->getFlashBag()->add(
            'success',
            array(
                'message' => 'connections deleted!',
            )
        );
    }
    else{
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );  
    }

    return $app->redirect($app['url_generator']->generate('connections_list'));

})
->bind('connections_delete');





$app->match('/connections/register', function () use ($app) {
    if("POST" == $app['request']->getMethod()){

        if (isset($_POST)) {
            $data = $_POST;
            if (isExist($_POST['ip'],$app)) {
            $update_query = "UPDATE `connections` SET `is_connected` = 1, `last_con`=NOW() WHERE `ip` =?";
            $app['db']->executeUpdate($update_query, array($data['ip']));            
            }else{
                  $update_query = "INSERT INTO `connections` (`ip`, `radio`, `tele`, `clients`, `last_con`, `is_connected`) VALUES (?, ?, ?, 1, NOW(), 1)";
                  $app['db']->executeUpdate($update_query, array($data['ip'], $data['radio'], $data['tele']));
            }
            return true;

        }
    }
    return false;      
})
->bind('connections_register');



$app->match('/connections/unregister', function () use ($app) {
$ip=$_POST['ip'];
 if (isExist($ip,$app)) {
            $update_query = "UPDATE `connections` SET `is_connected` = 0 WHERE `ip` = ?";
            $app['db']->executeUpdate($update_query, array($ip));   
            return true;         
            }

    return false;
        
})
->bind('connections_unregister');



$app->match('/connections/getserver', function () use ($app) {
            $update_query = "SELECT * FROM `connections` WHERE is_connected=1 ORDER BY last_con DESC LIMIT 1";
              $row_sql = $app['db']->fetchAssoc($update_query);   
              if (!$row_sql) {
                return "0";
            }
            return $row_sql['ip'];    
        
})
->bind('connections_getserver');


 function isExist($ip,$app)
{
 $find_sql = "SELECT * FROM `connections` WHERE `ip` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($ip));

    if(!$row_sql){
        return false;
    }
    return true;
}