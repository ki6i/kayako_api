<?php

require_once('class_KayakoAPI.php');


$kayako_api = new KayakoAPI(KAYAKO_URL, KAYAKO_API_KEY, KAYAKO_SECRET_KEY);

echo $kayako_api->POST(array('full_method_string' => '/Tickets/TicketSearch',
                             'query'              => 'email@email.com',
                             'creatoremail'       => '1'));

echo $kayako_api->GET(array('full_method_string' => '/Tickets/TicketStatus'));

}
