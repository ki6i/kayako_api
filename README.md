GitHub Kayako PHP Library
=============

This is a really light PHP Library for the new RESTful Kayako API.
If you have any questions, here is the blog site: http://blog.ki6i.com
API Documentation: http://wiki.kayako.com/display/DEV/REST+-+TicketSearch

Basic Usage
------------

See the file: examples.php

Definition of the library class:

$kayako_api = new KayakoAPI(KAYAKO_URL, KAYAKO_API_KEY, KAYAKO_SECRET_KEY);

Call one of the RESTful methods and the additional arguments: GET, PUT, POST, DELETE

echo $kayako_api->POST(array('full_method_string' => '/Tickets/TicketSearch',
                             'query'              => 'email@email.com',
                             'creatoremail'       => '1'));