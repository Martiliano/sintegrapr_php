<?php

require 'spider_pr.php';

$spider_pr = new Spider\Sintegra\Pr();

echo "\n\n Status: ";
print_r($spider_pr->get_status());

echo "\n\n Error: ";
print_r($spider_pr->get_error());

echo "\n\n ErrNo: ";
print_r($spider_pr->get_errno());

echo "\n\n Running...";

$spider_pr->searchByCnpj("99783168185198"); // DEVE SER INFORMADO UM CNPJ VALIDO CADASTRADO NO ESTADO DO PARANA

echo "\n\n Result: ";
print_r($spider_pr->get_result());

echo "\n\n Status: ";
print_r($spider_pr->get_status());

echo "\n\n Error: ";
print_r($spider_pr->get_error());

echo "\n\n ErrNo: ";
print_r($spider_pr->get_errno());

echo "\n\n";

?>