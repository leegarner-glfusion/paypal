<?php
/**
*   Database creation and update statements for the Paypal plugin.
*   Based on the gl-paypal Plugin for Geeklog CMS by Vincent Furia.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Vincent Furia <vinny01@users.sourceforge.net
*   @copyright  Copyright (c) 2009-2018 Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2005-2006 Vincent Furia
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_TABLES, $_SQL, $PP_UPGRADE, $_PP_SAMPLEDATA;
$_SQL = array();
$PP_UPGRADE = array();

// Move upgrade 0.5.4 SQL to the top so its large table creation can be used by the $_SQL array also
$PP_UPGRADE['0.5.4'] = array(
    "CREATE TABLE `{$_TABLES['paypal.currency']}` (
        `code` varchar(3) NOT NULL,
        `symbol` varchar(10) DEFAULT NULL,
        `name` varchar(255) DEFAULT NULL,
        `numeric_code` int(4) DEFAULT NULL,
        `symbol_placement` varchar(10) DEFAULT NULL,
        `symbol_spacer` varchar(2) DEFAULT ' ',
        `code_placement` varchar(10) DEFAULT 'after',
        `decimals` int(3) DEFAULT '2',
        `rounding_step` float(5,2) DEFAULT '0.00',
        `thousands_sep` varchar(2) DEFAULT ',',
        `decimal_sep` varchar(2) DEFAULT '.',
        `major_unit` varchar(20) DEFAULT NULL,
        `minor_unit` varchar(20) DEFAULT NULL,
        `conversion_rate` float(7,5) DEFAULT '1.00000',
        `conversion_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`code`)
    ) ENGINE=MyISAM",
    "INSERT INTO `{$_TABLES['paypal.currency']}` VALUES
    ('AED','?.?','United Arab Emirates Dirham',784,'hidden',' ','before',2,0.00,',','.','Dirham','Fils',1.00000,'2014-01-03 20:51:17'),
    ('AFN','Af','Afghan Afghani',971,'hidden',' ','after',0,0.00,',','.','Afghani','Pul',1.00000,'2014-01-03 20:54:44'),
	('ANG','NAf.','Netherlands Antillean Guilder',532,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('AOA','Kz','Angolan Kwanza',973,'hidden',' ','after',2,0.00,',','.','Kwanza','Cêntimo',1.00000,'2014-01-03 20:54:44'),
	('ARM','m\$n','Argentine Peso Moneda Nacional',NULL,'hidden',' ','after',2,0.00,',','.','Peso','Centavos',1.00000,'2014-01-03 20:54:44'),
	('ARS','AR$','Argentine Peso',32,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('AUD','$','Australian Dollar',36,'before',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('AWG','Afl.','Aruban Florin',533,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('AZN','man.','Azerbaijanian Manat',NULL,'hidden',' ','after',2,0.00,',','.','New Manat','Q?pik',1.00000,'2014-01-03 20:54:44'),
	('BAM','KM','Bosnia-Herzegovina Convertible Mark',977,'hidden',' ','after',2,0.00,',','.','Convertible Marka','Fening',1.00000,'2014-01-03 20:54:44'),
	('BBD','Bds$','Barbadian Dollar',52,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BDT','Tk','Bangladeshi Taka',50,'hidden',' ','after',2,0.00,',','.','Taka','Paisa',1.00000,'2014-01-03 20:54:44'),
	('BGN','??','Bulgarian lev',975,'after',' ','hidden',2,0.00,',',',','Lev','Stotinka',1.00000,'2014-01-03 20:49:55'),
	('BHD','BD','Bahraini Dinar',48,'hidden',' ','after',3,0.00,',','.','Dinar','Fils',1.00000,'2014-01-03 20:54:44'),
	('BIF','FBu','Burundian Franc',108,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('BMD','BD$','Bermudan Dollar',60,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BND','BN$','Brunei Dollar',96,'hidden',' ','after',2,0.00,',','.','Dollar','Sen',1.00000,'2014-01-03 20:54:44'),
	('BOB','Bs','Bolivian Boliviano',68,'hidden',' ','after',2,0.00,',','.','Bolivianos','Centavo',1.00000,'2014-01-03 20:54:44'),
	('BRL','R$','Brazilian Real',986,'before',' ','hidden',2,0.00,'.',',','Reais','Centavo',1.00000,'2014-01-03 20:49:55'),
	('BSD','BS$','Bahamian Dollar',44,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BTN','Nu.','Bhutanese Ngultrum',64,'hidden',' ','after',2,0.00,',','.','Ngultrum','Chetrum',1.00000,'2014-01-03 20:54:44'),
	('BWP','BWP','Botswanan Pula',72,'hidden',' ','after',2,0.00,',','.','Pulas','Thebe',1.00000,'2014-01-03 20:54:44'),
	('BYR','???.','Belarusian ruble',974,'after',' ','hidden',0,0.00,',','.','Ruble',NULL,1.00000,'2014-01-03 20:49:48'),
	('BZD','BZ$','Belize Dollar',84,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('CAD','CA$','Canadian Dollar',124,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('CDF','CDF','Congolese Franc',976,'hidden',' ','after',2,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('CHF','Fr.','Swiss Franc',756,'hidden',' ','after',2,0.05,',','.','Franc','Rappen',1.00000,'2014-01-03 20:54:44'),
	('CLP','CL$','Chilean Peso',152,'hidden',' ','after',0,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CNY','¥','Chinese Yuan Renminbi',156,'before',' ','hidden',2,0.00,',','.','Yuan','Fen',1.00000,'2014-01-03 20:49:55'),
	('COP','$','Colombian Peso',170,'before',' ','hidden',0,0.00,'.',',','Peso','Centavo',1.00000,'2014-01-03 20:49:48'),
	('CRC','¢','Costa Rican Colón',188,'hidden',' ','after',0,0.00,',','.','Colón','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('CUC','CUC$','Cuban Convertible Peso',NULL,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CUP','CU$','Cuban Peso',192,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CVE','CV$','Cape Verdean Escudo',132,'hidden',' ','after',2,0.00,',','.','Escudo','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CZK','K?','Czech Republic Koruna',203,'after',' ','hidden',2,0.00,',',',','Koruna','Halé?',1.00000,'2014-01-03 20:49:55'),
	('DJF','Fdj','Djiboutian Franc',262,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('DKK','kr.','Danish Krone',208,'after',' ','hidden',2,0.00,',',',','Kroner','Øre',1.00000,'2014-01-03 20:49:55'),
	('DOP','RD$','Dominican Peso',214,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('DZD','DA','Algerian Dinar',12,'hidden',' ','after',2,0.00,',','.','Dinar','Santeem',1.00000,'2014-01-03 20:54:44'),
	('EEK','Ekr','Estonian Kroon',233,'hidden',' ','after',2,0.00,',',',','Krooni','Sent',1.00000,'2014-01-03 20:54:44'),
	('EGP','EG£','Egyptian Pound',818,'hidden',' ','after',2,0.00,',','.','Pound','Piastr',1.00000,'2014-01-03 20:54:44'),
	('ERN','Nfk','Eritrean Nakfa',232,'hidden',' ','after',2,0.00,',','.','Nakfa','Cent',1.00000,'2014-01-03 20:54:44'),
	('ETB','Br','Ethiopian Birr',230,'hidden',' ','after',2,0.00,',','.','Birr','Santim',1.00000,'2014-01-03 20:54:44'),
	('EUR','€','Euro',978,'after',' ','hidden',2,0.00,',',',','Euro','Cent',1.00000,'2014-01-03 20:49:55'),
	('FJD','FJ$','Fijian Dollar',242,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('FKP','FK£','Falkland Islands Pound',238,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('GBP','£','British Pound Sterling',826,'before',' ','hidden',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:49:55'),
	('GHS','GH?','Ghanaian Cedi',NULL,'hidden',' ','after',2,0.00,',','.','Cedi','Pesewa',1.00000,'2014-01-03 20:54:44'),
	('GIP','GI£','Gibraltar Pound',292,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('GMD','GMD','Gambian Dalasi',270,'hidden',' ','after',2,0.00,',','.','Dalasis','Butut',1.00000,'2014-01-03 20:54:44'),
	('GNF','FG','Guinean Franc',324,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('GTQ','GTQ','Guatemalan Quetzal',320,'hidden',' ','after',2,0.00,',','.','Quetzales','Centavo',1.00000,'2014-01-03 20:54:44'),
	('GYD','GY$','Guyanaese Dollar',328,'hidden',' ','after',0,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('HKD','HK$','Hong Kong Dollar',344,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('HNL','HNL','Honduran Lempira',340,'hidden',' ','after',2,0.00,',','.','Lempiras','Centavo',1.00000,'2014-01-03 20:54:44'),
	('HRK','kn','Croatian Kuna',191,'hidden',' ','after',2,0.00,',','.','Kuna','Lipa',1.00000,'2014-01-03 20:54:44'),
	('HTG','HTG','Haitian Gourde',332,'hidden',' ','after',2,0.00,',','.','Gourde','Centime',1.00000,'2014-01-03 20:54:44'),
	('HUF','Ft','Hungarian Forint',348,'after',' ','hidden',0,0.00,',',',','Forint',NULL,1.00000,'2014-01-03 20:49:48'),
	('IDR','Rp','Indonesian Rupiah',360,'hidden',' ','after',0,0.00,',','.','Rupiahs','Sen',1.00000,'2014-01-03 20:54:44'),
	('ILS','?','Israeli New Shekel',376,'before',' ','hidden',2,0.00,',','.','New Shekels','Agora',1.00000,'2014-01-03 20:49:55'),
	('INR','Rs','Indian Rupee',356,'hidden',' ','after',2,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('IRR','?','Iranian Rial',364,'after',' ','hidden',2,0.00,',','.','Toman','Rial',1.00000,'2014-01-03 20:49:55'),
	('ISK','Ikr','Icelandic Króna',352,'hidden',' ','after',0,0.00,',','.','Kronur','Eyrir',1.00000,'2014-01-03 20:54:44'),
	('JMD','J$','Jamaican Dollar',388,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('JOD','JD','Jordanian Dinar',400,'hidden',' ','after',3,0.00,',','.','Dinar','Piastr',1.00000,'2014-01-03 20:54:44'),
	('JPY','¥','Japanese Yen',392,'before',' ','hidden',0,0.00,',','.','Yen','Sen',1.00000,'2014-01-03 20:49:48'),
	('KES','Ksh','Kenyan Shilling',404,'hidden',' ','after',2,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('KGS','???','Kyrgyzstani Som',417,'after',' ','hidden',2,0.00,',','.','Som','Tyiyn',1.00000,'2014-01-03 20:49:55'),
	('KMF','CF','Comorian Franc',174,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('KRW','?','South Korean Won',410,'hidden',' ','after',0,0.00,',','.','Won','Jeon',1.00000,'2014-01-03 20:54:44'),
	('KWD','KD','Kuwaiti Dinar',414,'hidden',' ','after',3,0.00,',','.','Dinar','Fils',1.00000,'2014-01-03 20:54:44'),
	('KYD','KY$','Cayman Islands Dollar',136,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('KZT','??.','Kazakhstani tenge',398,'after',' ','hidden',2,0.00,',',',','Tenge','Tiyn',1.00000,'2014-01-03 20:49:55'),
	('LAK','?N','Laotian Kip',418,'hidden',' ','after',0,0.00,',','.','Kips','Att',1.00000,'2014-01-03 20:54:44'),
	('LBP','LB£','Lebanese Pound',422,'hidden',' ','after',0,0.00,',','.','Pound','Piastre',1.00000,'2014-01-03 20:54:44'),
	('LKR','SLRs','Sri Lanka Rupee',144,'hidden',' ','after',2,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('LRD','L$','Liberian Dollar',430,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('LSL','LSL','Lesotho Loti',426,'hidden',' ','after',2,0.00,',','.','Loti','Sente',1.00000,'2014-01-03 20:54:44'),
	('LTL','Lt','Lithuanian Litas',440,'hidden',' ','after',2,0.00,',','.','Litai','Centas',1.00000,'2014-01-03 20:54:44'),
	('LVL','Ls','Latvian Lats',428,'hidden',' ','after',2,0.00,',','.','Lati','Santims',1.00000,'2014-01-03 20:54:44'),
	('LYD','LD','Libyan Dinar',434,'hidden',' ','after',3,0.00,',','.','Dinar','Dirham',1.00000,'2014-01-03 20:54:44'),
	('MAD',' Dhs','Moroccan Dirham',504,'after',' ','hidden',2,0.00,',','.','Dirhams','Santimat',1.00000,'2014-01-03 20:49:55'),
	('MDL','MDL','Moldovan leu',498,'after',' ','hidden',2,0.00,',','.','Lei','bani',1.00000,'2014-01-03 20:49:55'),
	('MMK','MMK','Myanma Kyat',104,'hidden',' ','after',0,0.00,',','.','Kyat','Pya',1.00000,'2014-01-03 20:54:44'),
	('MNT','?','Mongolian Tugrik',496,'hidden',' ','after',0,0.00,',','.','Tugriks','Möngö',1.00000,'2014-01-03 20:54:44'),
	('MOP','MOP$','Macanese Pataca',446,'hidden',' ','after',2,0.00,',','.','Pataca','Avo',1.00000,'2014-01-03 20:54:44'),
	('MRO','UM','Mauritanian Ouguiya',478,'hidden',' ','after',0,0.00,',','.','Ouguiya','Khoums',1.00000,'2014-01-03 20:54:44'),
	('MTP','MT£','Maltese Pound',NULL,'hidden',' ','after',2,0.00,',','.','Pound','Shilling',1.00000,'2014-01-03 20:54:44'),
	('MUR','MURs','Mauritian Rupee',480,'hidden',' ','after',0,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('MXN','$','Mexican Peso',484,'before',' ','hidden',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:49:55'),
	('MYR','RM','Malaysian Ringgit',458,'before',' ','hidden',2,0.00,',','.','Ringgits','Sen',1.00000,'2014-01-03 20:49:55'),
	('MZN','MTn','Mozambican Metical',NULL,'hidden',' ','after',2,0.00,',','.','Metical','Centavo',1.00000,'2014-01-03 20:54:44'),
	('NAD','N$','Namibian Dollar',516,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('NGN','?','Nigerian Naira',566,'hidden',' ','after',2,0.00,',','.','Naira','Kobo',1.00000,'2014-01-03 20:54:44'),
	('NIO','C$','Nicaraguan Cordoba Oro',558,'hidden',' ','after',2,0.00,',','.','Cordoba','Centavo',1.00000,'2014-01-03 20:54:44'),
	('NOK','Nkr','Norwegian Krone',578,'hidden',' ','after',2,0.00,',',',','Krone','Øre',1.00000,'2014-01-03 20:54:44'),
	('NPR','NPRs','Nepalese Rupee',524,'hidden',' ','after',2,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('NZD','NZ$','New Zealand Dollar',554,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('PAB','B/.','Panamanian Balboa',590,'hidden',' ','after',2,0.00,',','.','Balboa','Centésimo',1.00000,'2014-01-03 20:54:44'),
	('PEN','S/.','Peruvian Nuevo Sol',604,'before',' ','hidden',2,0.00,',','.','Nuevos Sole','Céntimo',1.00000,'2014-01-03 20:49:55'),
	('PGK','PGK','Papua New Guinean Kina',598,'hidden',' ','after',2,0.00,',','.','Kina ','Toea',1.00000,'2014-01-03 20:54:44'),
	('PHP','?','Philippine Peso',608,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('PKR','PKRs','Pakistani Rupee',586,'hidden',' ','after',0,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('PLN','z?','Polish Z?oty',985,'after',' ','hidden',2,0.00,',',',','Z?otych','Grosz',1.00000,'2014-01-03 20:49:55'),
	('PYG','?','Paraguayan Guarani',600,'hidden',' ','after',0,0.00,',','.','Guarani','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('QAR','QR','Qatari Rial',634,'hidden',' ','after',2,0.00,',','.','Rial','Dirham',1.00000,'2014-01-03 20:54:44'),
	('RHD','RH$','Rhodesian Dollar',NULL,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('RON','RON','Romanian Leu',NULL,'hidden',' ','after',2,0.00,',','.','Leu','Ban',1.00000,'2014-01-03 20:54:44'),
	('RSD','din.','Serbian Dinar',NULL,'hidden',' ','after',0,0.00,',','.','Dinars','Para',1.00000,'2014-01-03 20:54:44'),
	('RUB','???.','Russian Ruble',643,'after',' ','hidden',2,0.00,',',',','Ruble','Kopek',1.00000,'2014-01-03 20:49:55'),
	('SAR','SR','Saudi Riyal',682,'hidden',' ','after',2,0.00,',','.','Riyals','Hallallah',1.00000,'2014-01-03 20:54:44'),
	('SBD','SI$','Solomon Islands Dollar',90,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SCR','SRe','Seychellois Rupee',690,'hidden',' ','after',2,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('SDD','LSd','Old Sudanese Dinar',736,'hidden',' ','after',2,0.00,',','.','Dinar','None',1.00000,'2014-01-03 20:54:44'),
	('SEK','kr','Swedish Krona',752,'after',' ','hidden',2,0.00,',',',','Kronor','Öre',1.00000,'2014-01-03 20:49:55'),
	('SGD','S$','Singapore Dollar',702,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SHP','SH£','Saint Helena Pound',654,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('SLL','Le','Sierra Leonean Leone',694,'hidden',' ','after',0,0.00,',','.','Leone','Cent',1.00000,'2014-01-03 20:54:44'),
	('SOS','Ssh','Somali Shilling',706,'hidden',' ','after',0,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('SRD','SR$','Surinamese Dollar',NULL,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SRG','Sf','Suriname Guilder',740,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('STD','Db','São Tomé and Príncipe Dobra',678,'hidden',' ','after',0,0.00,',','.','Dobra','Cêntimo',1.00000,'2014-01-03 20:54:44'),
	('SYP','SY£','Syrian Pound',760,'hidden',' ','after',0,0.00,',','.','Pound','Piastre',1.00000,'2014-01-03 20:54:44'),
	('SZL','SZL','Swazi Lilangeni',748,'hidden',' ','after',2,0.00,',','.','Lilangeni','Cent',1.00000,'2014-01-03 20:54:44'),
	('THB','?','Thai Baht',764,'hidden',' ','after',2,0.00,',','.','Baht','Satang',1.00000,'2014-01-03 20:54:44'),
	('TND','DT','Tunisian Dinar',788,'hidden',' ','after',3,0.00,',','.','Dinar','Millime',1.00000,'2014-01-03 20:54:44'),
	('TOP','T$','Tongan Pa?anga',776,'hidden',' ','after',2,0.00,',','.','Pa?anga','Senit',1.00000,'2014-01-03 20:54:44'),
	('TRY','TL','Turkish Lira',949,'after',' ','',2,0.00,'.',',','Lira','Kurus',1.00000,'2014-01-03 20:49:55'),
	('TTD','TT$','Trinidad and Tobago Dollar',780,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('TWD','NT$','New Taiwan Dollar',901,'hidden',' ','after',2,0.00,',','.','New Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('TZS','TSh','Tanzanian Shilling',834,'hidden',' ','after',0,0.00,',','.','Shilling','Senti',1.00000,'2014-01-03 20:54:44'),
	('UAH','???.','Ukrainian Hryvnia',980,'after',' ','hidden',2,0.00,',','.','Hryvnia','Kopiyka',1.00000,'2014-01-03 20:49:55'),
	('UGX','USh','Ugandan Shilling',800,'hidden',' ','after',0,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('USD','$','United States Dollar',840,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('UYU','\$U','Uruguayan Peso',858,'hidden',' ','after',2,0.00,',','.','Peso','Centésimo',1.00000,'2014-01-03 20:54:44'),
	('VEF','Bs.F.','Venezuelan Bolívar Fuerte',NULL,'hidden',' ','after',2,0.00,',','.','Bolivares Fuerte','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('VND','?','Vietnamese Dong',704,'after','','hidden',0,0.00,'.','.','Dong','Hà',1.00000,'2014-01-03 20:53:33'),
	('VUV','VT','Vanuatu Vatu',548,'hidden',' ','after',0,0.00,',','.','Vatu',NULL,1.00000,'2014-01-03 20:54:44'),
	('WST','WS$','Samoan Tala',882,'hidden',' ','after',2,0.00,',','.','Tala','Sene',1.00000,'2014-01-03 20:54:44'),
	('XAF','FCFA','CFA Franc BEAC',950,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('XCD','EC$','East Caribbean Dollar',951,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('XOF','CFA','CFA Franc BCEAO',952,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('XPF','CFPF','CFP Franc',953,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('YER','YR','Yemeni Rial',886,'hidden',' ','after',0,0.00,',','.','Rial','Fils',1.00000,'2014-01-03 20:54:44'),
	('ZAR','R','South African Rand',710,'before',' ','hidden',2,0.00,',','.','Rand','Cent',1.00000,'2014-01-03 20:49:55'),
	('ZMK','ZK','Zambian Kwacha',894,'hidden',' ','after',0,0.00,',','.','Kwacha','Ngwee',1.00000,'2014-01-03 20:54:44');",
    "ALTER TABLE `{$_TABLES['paypal.products']}` ADD sale_price DECIMAL(15,4)",
);

$_SQL['paypal.ipnlog'] = "CREATE TABLE {$_TABLES['paypal.ipnlog']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_addr` varchar(15) NOT NULL,
  `ts` int(11) unsigned,
  `verified` tinyint(1) DEFAULT '0',
  `txn_id` varchar(255) DEFAULT NULL,
  `gateway` varchar(25) DEFAULT NULL,
  `ipn_data` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ipnlog_ts` (`ts`),
  KEY `ipnlog_txnid` (`txn_id`)
) ENGINE=MyISAM";

$_SQL['paypal.products'] = "CREATE TABLE {$_TABLES['paypal.products']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `cat_id` int(11) unsigned NOT NULL DEFAULT '0',
  `short_description` varchar(255) DEFAULT NULL,
  `description` text,
  `keywords` varchar(255) DEFAULT '',
  `price` decimal(12,4) unsigned DEFAULT NULL,
  `prod_type` tinyint(2) DEFAULT '0',
  `file` varchar(255) DEFAULT NULL,
  `expiration` int(11) DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT '1',
  `featured` tinyint(1) unsigned DEFAULT '0',
  `dt_add` datetime NOT NULL,
  `views` int(4) unsigned DEFAULT '0',
  `comments_enabled` tinyint(1) DEFAULT '0',
  `rating_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `buttons` text,
  `rating` double(6,4) NOT NULL DEFAULT '0.0000',
  `votes` int(11) unsigned NOT NULL DEFAULT '0',
  `weight` decimal(9,4) DEFAULT '0.0000',
  `taxable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `shipping_type` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `shipping_amt` decimal(9,4) unsigned NOT NULL DEFAULT '0.0000',
  `shipping_units` decimal(9,4) unsigned NOT NULL DEFAULT '0.0000',
  `show_random` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `show_popular` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `options` text,
  `track_onhand` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `onhand` int(10) unsigned DEFAULT '0',
  `oversell` tinyint(1) NOT NULL DEFAULT '0',
  `qty_discounts` text,
  `custom` varchar(255) NOT NULL DEFAULT '',
  `avail_beg` date DEFAULT '1900-01-01',
  `avail_end` date DEFAULT '9999-12-31',
  PRIMARY KEY (`id`),
  KEY `products_name` (`name`),
  KEY `products_price` (`price`),
  KEY `avail_beg` (`avail_beg`),
  KEY `avail_end` (`avail_end`)
) ENGINE=MyISAM";

$_SQL['paypal.purchases'] = "CREATE TABLE {$_TABLES['paypal.purchases']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(40) NOT NULL,
  `product_id` varchar(128) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `txn_id` varchar(128) DEFAULT '',
  `txn_type` varchar(255) DEFAULT '',
  `status` varchar(255) DEFAULT NULL,
  `expiration` int(11) unsigned NOT NULL DEFAULT '0',
  `price` float(9,4) NOT NULL DEFAULT '0.0000',
  `taxable` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `token` varchar(40) NOT NULL DEFAULT '',
  `options` varchar(40) DEFAULT '',
  `options_text` text,
  `extras` text,
  `shipping` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `handling` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `tax` decimal(9,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `purchases_productid` (`product_id`),
  KEY `purchases_txnid` (`txn_id`)
) ENGINE=MyISAM";

$_SQL['paypal.images'] = "CREATE TABLE {$_TABLES['paypal.images']} (
  `img_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`img_id`),
  KEY `idxProd` (`product_id`,`img_id`)
) ENGINE=MyISAM";

/*$_SQL['paypal.prodXcat'] = "CREATE TABLE {$_TABLES['paypal.prodXcat']} (
  `prod_id` int(11) unsigned NOT NULL,
  `cat_id` int(11) unsigned NOT NULL,
  PRIMARY KEY  (`prod_id`,`cat_id`)
)";*/

$_SQL['paypal.categories'] = "CREATE TABLE {$_TABLES['paypal.categories']} (
  `cat_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` smallint(5) unsigned DEFAULT '0',
  `cat_name` varchar(128) DEFAULT '',
  `description` text,
  `enabled` tinyint(1) unsigned DEFAULT '1',
  `grp_access` mediumint(8) unsigned NOT NULL DEFAULT '1',
  `image` varchar(255) DEFAULT '',
  `lft` smallint(5) unsigned NOT NULL DEFAULT '0',
  `rgt` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`cat_id`),
  KEY `idxName` (`cat_name`,`cat_id`),
  KEY `cat_lft` (`lft`),
  KEY `cat_rgt` (`rgt`)
) ENGINE=MyISAM";

// since 0.4.5
$_SQL['paypal.prod_attr'] = "CREATE TABLE `{$_TABLES['paypal.prod_attr']}` (
  `attr_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(11) unsigned DEFAULT NULL,
  `attr_name` varchar(64) DEFAULT NULL,
  `attr_value` varchar(64) DEFAULT NULL,
  `orderby` int(3) unsigned DEFAULT NULL,
  `attr_price` decimal(9,4) DEFAULT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`attr_id`),
  UNIQUE KEY `item_id` (`item_id`,`attr_name`,`attr_value`)
) ENGINE=MyISAM";

// since 0.5.0
$_SQL['paypal.buttons'] = "CREATE TABLE `{$_TABLES['paypal.buttons']}` (
  `pi_name` varchar(20) NOT NULL DEFAULT 'paypal',
  `item_id` varchar(40) NOT NULL,
  `gw_name` varchar(10) NOT NULL DEFAULT '',
  `btn_key` varchar(20) NOT NULL,
  `button` text,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pi_name`,`item_id`,`gw_name`,`btn_key`)
) ENGINE=MyISAM";

// since 0.5.0
$_SQL['paypal.orders'] = "CREATE TABLE `{$_TABLES['paypal.orders']}` (
  `order_id` varchar(40) NOT NULL,
  `uid` int(11) NOT NULL DEFAULT '0',
  `order_date` int(11) unsigned NOT NULL DEFAULT '0',
  `last_mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `billto_id` int(11) unsigned NOT NULL DEFAULT '0',
  `billto_name` varchar(255) DEFAULT NULL,
  `billto_company` varchar(255) DEFAULT NULL,
  `billto_address1` varchar(255) DEFAULT NULL,
  `billto_address2` varchar(255) DEFAULT NULL,
  `billto_city` varchar(255) DEFAULT NULL,
  `billto_state` varchar(255) DEFAULT NULL,
  `billto_country` varchar(255) DEFAULT NULL,
  `billto_zip` varchar(40) DEFAULT NULL,
  `shipto_id` int(11) unsigned NOT NULL DEFAULT '0',
  `shipto_name` varchar(255) DEFAULT NULL,
  `shipto_company` varchar(255) DEFAULT NULL,
  `shipto_address1` varchar(255) DEFAULT NULL,
  `shipto_address2` varchar(255) DEFAULT NULL,
  `shipto_city` varchar(255) DEFAULT NULL,
  `shipto_state` varchar(255) DEFAULT NULL,
  `shipto_country` varchar(255) DEFAULT NULL,
  `shipto_zip` varchar(40) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `tax` decimal(9,4) unsigned DEFAULT NULL,
  `shipping` decimal(9,4) unsigned DEFAULT NULL,
  `handling` decimal(9,4) unsigned DEFAULT NULL,
  `by_gc` decimal(12,4) unsigned DEFAULT NULL,
  `status` varchar(25) DEFAULT 'pending',
  `pmt_method` varchar(20) DEFAULT NULL,
  `pmt_txn_id` varchar(255) DEFAULT NULL,
  `instructions` text,
  `token` varchar(20) DEFAULT NULL,
  `tax_rate` decimal(7,5) NOT NULL DEFAULT '0.00000',
  `info` text,
  `currency` varchar(5) NOT NULL DEFAULT 'USD',
  `order_seq` int(11) UNSIGNED,
  PRIMARY KEY (`order_id`),
  KEY (`order_date`),
  UNIQUE (order_seq)
) ENGINE=MyISAM";

// since 0.5.0
$_SQL['paypal.address'] = "CREATE TABLE `{$_TABLES['paypal.address']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `zip` varchar(40) DEFAULT NULL,
  `billto_def` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `shipto_def` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`,`zip`)
) ENGINE=MyISAM";

// since 0.5.0
$_SQL['paypal.userinfo'] = "CREATE TABLE `{$_TABLES['paypal.userinfo']}` (
  `uid` int(11) unsigned NOT NULL,
  `cart` text,
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM";

// since .5.0
$_SQL['paypal.gateways'] = "CREATE TABLE `{$_TABLES['paypal.gateways']}` (
  `id` varchar(25) NOT NULL,
  `orderby` int(3) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `description` varchar(255) DEFAULT NULL,
  `config` text,
  `services` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM";

// since 0.5.0
$_SQL['paypal.workflows'] = "CREATE TABLE `{$_TABLES['paypal.workflows']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `wf_name` varchar(40) DEFAULT NULL,
  `orderby` int(2) DEFAULT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `can_disable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM";

// since 0.5.2
$_SQL['paypal.orderstatus'] = "CREATE TABLE `{$_TABLES['paypal.orderstatus']}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orderby` int(3) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `name` varchar(20) NOT NULL,
  `notify_buyer` tinyint(1) NOT NULL DEFAULT '1',
  `notify_admin` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM";

// since 0.5.2
$_SQL['paypal.order_log'] = "CREATE TABLE `{$_TABLES['paypal.order_log']}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ts` int(11) unsigned DEFAULT NULL,
  `order_id` varchar(40) DEFAULT NULL,
  `username` varchar(60) NOT NULL DEFAULT '',
  `message` text,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM";

// since 0.5.4
$_SQL['paypal.currency'] = $PP_UPGRADE['0.5.4'][0];

// since 0.6.0
$_SQL['paypal.coupons'] = "CREATE TABLE `{$_TABLES['paypal.coupons']}` (
  `code` varchar(128) NOT NULL,
  `amount` decimal(12,4) unsigned NOT NULL DEFAULT '0.0000',
  `balance` decimal(12,4) unsigned NOT NULL DEFAULT '0.0000',
  `buyer` int(11) unsigned NOT NULL DEFAULT '0',
  `redeemer` int(11) unsigned NOT NULL DEFAULT '0',
  `purchased` int(11) unsigned NOT NULL DEFAULT '0',
  `redeemed` int(11) unsigned NOT NULL DEFAULT '0',
  `expires` date DEFAULT '9999-12-31',
  PRIMARY KEY (`code`),
  KEY `owner` (`redeemer`,`balance`,`expires`),
  KEY `purchased` (`purchased`)
) ENGINE=MyIsam";

// since 0.6.0
$_SQL['paypal.coupon_log'] = "CREATE TABLE {$_TABLES['paypal.coupon_log']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `code` varchar(128) NOT NULL,
  `ts` int(11) unsigned DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `amount` float(8,2) DEFAULT NULL,
  `msg` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `code` (`code`)
) ENGINE=MyIsam";

// since 0.6.0
$_SQL['paypal.sales'] = "CREATE TABLE {$_TABLES['paypal.sales']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40),
  `item_type` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `start` int(11) unsigned DEFAULT NULL,
  `end` int(11) unsigned DEFAULT NULL,
  `discount_type` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `amount` decimal(6,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_type` (`item_type`,`item_id`,`start`,`end`)
) ENGINE=MyIsam";

// since 0.6.0+
$_SQL['paypal.shipping'] = "CREATE TABLE `{$_TABLES['paypal.shipping']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `min_units` int(11) unsigned NOT NULL DEFAULT '0',
  `max_units` int(11) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `rates` text,
  PRIMARY KEY (`id`)
) ENGINE=MyIsam";

// Sample data to load up the Paypal gateway configuration
$_PP_SAMPLEDATA = array(
    "INSERT INTO {$_TABLES['paypal.categories']}
            (cat_id, parent_id, cat_name, description, grp_access, lft, rgt)
        VALUES
            (1, 0, 'Home', 'Root Category', 2, 1, 2)",
/*    "INSERT INTO {$_TABLES['paypal.gateways']}
            (id, orderby, enabled, description, config, services)
        VALUES
            ('paypal', 10, 0, 'Paypal Website Payments Standard', '',
             'a:6:{s:7:\"buy_now\";s:1:\"1\";s:8:\"donation\";s:1:\"1\";s:7:\"pay_now\";s:1:\"1\";s:9:\"subscribe\";s:1:\"1\";s:8:\"checkout\";s:1:\"1\";s:8:\"external\";s:1:\"1\";}')",*/
    "INSERT INTO {$_TABLES['paypal.workflows']}
            (id, wf_name, orderby, enabled, can_disable)
        VALUES
            (1, 'viewcart', 10, 3, 0),
            (2, 'billto', 20, 0, 1),
            (3, 'shipto', 30, 2, 1)",
    "INSERT INTO {$_TABLES['paypal.orderstatus']}
            (id, orderby, enabled, name, notify_buyer, notify_admin)
        VALUES
            (1, 10, 1, 'pending', 0, 0),
            (2, 20, 1, 'paid', 1, 1),
            (3, 30, 1, 'processing', 1, 0),
            (4, 40, 1, 'shipped', 1, 0),
            (5, 50, 1, 'closed', 0, 0),
            (6, 60, 1, 'refunded', 0, 0)",
    $PP_UPGRADE['0.5.4'][1],
    "INSERT INTO `{$_TABLES['paypal.shipping']}` VALUES
        (1,'USPS Priority Flat Rate',0.0001,50.0000,0,'[{\"dscp\":\"Small\",\"units\":5,\"rate\":7.2},{\"dscp\":\"Medium\",\"units\":20,\"rate\":13.65},{\"dscp\":\"Large\",\"units\":50,\"rate\":18.9}]')",
);


// Upgrade information for version 0.1.1 to version 0.2
$PP_UPGRADE['0.2'] = array(
    "ALTER TABLE {$_TABLES['paypal.purchases']}
        ADD COLUMN quantity int NOT NULL DEFAULT 1 AFTER product_id",
    "ALTER TABLE {$_TABLES['paypal.products']}
        ADD COLUMN category varchar(80) AFTER name,
        ADD KEY `products_category` (category)",
);

$PP_UPGRADE['0.4.0'] = array(
    $_SQL['paypal.images'],
    //$_SQL['paypal.prodXcat'],
    $_SQL['paypal.categories'],
    "ALTER TABLE {$_TABLES['paypal.products']}
        CHANGE download prod_type tinyint(2) default 0,
        ADD `enabled` tinyint(1) default 1,
        ADD `featured` tinyint(1) unsigned default '0',
        ADD `dt_add` INT(11) UNSIGNED,
        ADD `views` INT(4) UNSIGNED DEFAULT 0,
        ADD `comments` int(5) unsigned default '0',
        ADD `comments_enabled` tinyint(1) unsigned default '0',
        ADD `buttons` text,
        ADD `rating` double(6,4) NOT NULL default '0.0000',
        ADD `votes` int(11) unsigned NOT NULL default '0',
        ADD `weight` float(6,2) default '0' AFTER `prod_type`,
        ADD `taxable` tinyint(1) unsigned NOT NULL default '1',
        ADD `shipping_type` tinyint(1) unsigned NOT NULL default '0',
        ADD `shipping_amt` float(6,2) unsigned NOT NULL default '0',
        ADD `cat_id` int(11) unsigned NOT NULL default '0' AFTER `name`,
        ADD `keywords` varchar(255) default '' AFTER `description`,
        DROP `small_pic`,
        DROP `picture`,
        DROP `physical`",
    "ALTER TABLE {$_TABLES['paypal.purchases']}
        CHANGE `product_id` `product_id` varchar(255) not null,
        ADD `txn_type` varchar(255) default '' AFTER `txn_id`,
        ADD `price` float(10,2) NOT NULL DEFAULT 0",
    "INSERT INTO {$_TABLES['blocks']}
        (type, name, title, tid, phpblockfn, is_enabled,
        owner_id, group_id,
        perm_owner, perm_group, perm_members, perm_anon)
    VALUES
        ('phpblock', 'paypal_featured', 'Featured Product', 'all',
            'phpblock_paypal_featured', 0, 2, 13, 3, 2, 2, 2),
        ('phpblock', 'paypal_random', 'Random Product', 'all',
            'phpblock_paypal_random', 0, 2, 13, 3, 2, 2, 2),
        ('phpblock', 'paypal_categories', 'Product Categories', 'all',
            'phpblock_paypal_categories', 0, 2, 13, 3, 2, 2, 2)",
);

$PP_UPGRADE['0.4.1'] = array(
    "ALTER TABLE {$_TABLES['paypal.products']}
        ADD `show_random` tinyint(1) unsigned NOT NULL default '1',
        ADD `show_popular` tinyint(1) unsigned NOT NULL default '1',
        CHANGE comments_enabled comments_enabled tinyint(1)",
    "UPDATE {$_TABLES['paypal.products']}
        SET comments_enabled=-1 WHERE comments_enabled=2",
    "ALTER TABLE {$_TABLES['paypal.purchases']}
        ADD description varchar(255) AFTER product_id",
    "UPDATE {$_TABLES['conf_values']} SET
            fieldset=50,
            sort_order=20
        WHERE
            name='debug_ipn' AND group_name='" . $_PP_CONF['pi_name'] . "'",
);

$PP_UPGRADE['0.4.2'] = array(
    "ALTER TABLE {$_TABLES['paypal.products']}
        ADD `rating_enabled` tinyint(1) unsigned NOT NULL default '1'
            AFTER `comments_enabled`",
);
$PP_UPGRADE['0.4.3'] = array(
    "ALTER TABLE {$_TABLES['paypal.purchases']}
        ADD description varchar(255) AFTER product_id",
);

$PP_UPGRADE['0.4.4'] = array(
    "ALTER TABLE {$_TABLES['paypal.purchases']}
        ADD `token` varchar(40) AFTER `price`",
    "ALTER TABLE {$_TABLES['paypal.products']}
        ADD `options` text",
);

$PP_UPGRADE['0.4.5'] = array(
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['paypal.prod_attr']}` (
      `attr_id` int(11) unsigned NOT NULL auto_increment,
      `item_id` int(11) unsigned default NULL,
      `attr_name` varchar(32) default NULL,
      `attr_value` varchar(32) default NULL,
      `orderby` int(3) unsigned default NULL,
      `attr_price` float(8,2) default NULL,
      `enabled` tinyint(1) unsigned NOT NULL default '1',
      PRIMARY KEY  (`attr_id`),
      UNIQUE KEY `item_id` (`item_id`,`attr_name`, `attr_value`)
    ) ENGINE=MyISAM",
);

$PP_UPGRADE['0.4.6'] = array(
    "UPDATE {$_TABLES['paypal.products']} SET prod_type=4 WHERE prod_type=2",
    "UPDATE {$_TABLES['paypal.products']} SET prod_type=2 WHERE prod_type=1",
    "UPDATE {$_TABLES['paypal.products']} SET prod_type=1 WHERE prod_type=0",
);

$PP_UPGRADE['0.5.0'] = array(
    "CREATE TABLE `{$_TABLES['paypal.buttons']}` (
      `item_id` int(11) NOT NULL,
      `gw_name` varchar(10) NOT NULL,
      `button` text,
      `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`item_id`,`gw_name`)) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['paypal.orders']}` (
      `order_id` varchar(40) NOT NULL,
      `uid` int(11) NOT NULL DEFAULT '0',
      `order_date` datetime NOT NULL,
      `last_mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `billto_name` varchar(255) DEFAULT NULL,
      `billto_company` varchar(255) DEFAULT NULL,
      `billto_address1` varchar(255) DEFAULT NULL,
      `billto_address2` varchar(255) DEFAULT NULL,
      `billto_city` varchar(255) DEFAULT NULL,
      `billto_state` varchar(255) DEFAULT NULL,
      `billto_country` varchar(255) DEFAULT NULL,
      `billto_zip` varchar(40) DEFAULT NULL,
      `shipto_name` varchar(255) DEFAULT NULL,
      `shipto_company` varchar(255) DEFAULT NULL,
      `shipto_address1` varchar(255) DEFAULT NULL,
      `shipto_address2` varchar(255) DEFAULT NULL,
      `shipto_city` varchar(255) DEFAULT NULL,
      `shipto_state` varchar(255) DEFAULT NULL,
      `shipto_country` varchar(255) DEFAULT NULL,
      `shipto_zip` varchar(40) DEFAULT NULL,
      `phone` varchar(30) DEFAULT NULL,
      `tax` decimal(9,4) unsigned DEFAULT NULL,
      `shipping` decimal(9,4) unsigned DEFAULT NULL,
      `handling` decimal(9,4) unsigned DEFAULT NULL,
      `status` varchar(25) DEFAULT 'pending',
      `pmt_method` varchar(20) DEFAULT NULL,
      `pmt_txn_id` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`order_id`) ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['paypal.address']}` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `uid` int(11) unsigned NOT NULL DEFAULT '1',
      `name` varchar(255) DEFAULT NULL,
      `company` varchar(255) DEFAULT NULL,
      `address1` varchar(255) DEFAULT NULL,
      `address2` varchar(255) DEFAULT NULL,
      `city` varchar(255) DEFAULT NULL,
      `state` varchar(255) DEFAULT NULL,
      `country` varchar(255) DEFAULT NULL,
      `zip` varchar(40) DEFAULT NULL,
      `billto_def` tinyint(1) unsigned NOT NULL DEFAULT '0',
      `shipto_def` tinyint(1) unsigned NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`) ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['paypal.userinfo']}` (
      `uid` int(11) unsigned NOT NULL,
      `cart` text,
      PRIMARY KEY (`uid`)) ENGINE=MyISAM",
    "ALTER TABLE `{$_TABLES['paypal.products']}`
        ADD `purch_grp` int(11) unsigned DEFAULT 1 AFTER `options`",
    "CREATE TABLE `{$_TABLES['paypal.gateways']}` (
      `id` varchar(25) NOT NULL,
      `orderby` int(3) NOT NULL DEFAULT '0',
      `enabled` tinyint(1) NOT NULL DEFAULT '1',
      `description` varchar(255) DEFAULT NULL,
      `config` text,
      `services` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `orderby` (`orderby`) ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['paypal.workflows']}` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `wf_name` varchar(40) DEFAULT NULL,
      `orderby` int(2) DEFAULT NULL,
      `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
      PRIMARY KEY (`id`),
      KEY `orderby` (`orderby`) ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['paypal.cart']}` (
      `cart_id` varchar(40) NOT NULL,
      `cart_uid` int(11) unsigned NOT NULL,
      `cart_order_id` varchar(20) DEFAULT NULL,
      `cart_info` text,
      `cart_contents` text,
      `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`cart_id`)) ENGINE=MyISAM",
    "ALTER TABLE {$_TABLES['paypal.purchases']}
        ADD order_id varchar(40) NOT NULL AFTER `id`,
        ADD options varchar(40) default '',
        ADD KEY (`order_id`)",
    "INSERT INTO {$_TABLES['paypal.workflows']}
            (id, wf_name, orderby, enabled)
        VALUES
            (1, 'viewcart', 10, 0),
            (2, 'billto', 20, 0),
            (3, 'shipto', 30, 0)",
    "ALTER TABLE {$_TABLES['paypal.ipnlog']}
        ADD `gateway` varchar(25) AFTER `txn_id`",
    "UPDATE {$_TABLES['paypal.ipnlog']} SET gateway='paypal'",
    "INSERT INTO {$_TABLES['blocks']}
            (bid, is_enabled, name, type, title,
            tid, blockorder, content,
            rdfurl, rdfupdated, onleft, phpblockfn, help, group_id, owner_id,
            perm_owner, perm_group, perm_members,perm_anon)
        VALUES
            ('', 1, 'paypal_cart', 'phpblock', 'Shopping Cart',
            'all', 5, '',
            '', '', 1, 'phpblock_paypal_cart', '', 13, 2,
            3, 2, 2, 2)",
);

$PP_UPGRADE['0.5.2'] = array(
    "ALTER TABLE {$_TABLES['paypal.orders']}
        ADD buyer_email varchar(255) default '' after phone,
        CHANGE status status varchar(25) not null default 'pending'",
    "UPDATE {$_TABLES['paypal.orders']} SET status='paid'",
    "CREATE TABLE `{$_TABLES['paypal.orderstatus']}` (
        `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `orderby` INT(3) UNSIGNED NOT NULL DEFAULT '0',
        `enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
        `name` VARCHAR(20) NOT NULL,
        `notify_buyer` TINYINT(1) NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        KEY `orderby` (`orderby`) ) ENGINE=MyISAM",
    "INSERT INTO {$_TABLES['paypal.orderstatus']}
            (id, orderby, enabled, name, notify_buyer)
        VALUES
            (1, 10, 1, 'pending', 0),
            (2, 20, 1, 'paid', 1),
            (3, 30, 1, 'processing', 0),
            (4, 40, 1, 'shipped', 0),
            (5, 50, 1, 'closed', 0)",
    "CREATE TABLE `{$_TABLES['paypal.order_log']}` (
        `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `order_id` VARCHAR(40) NULL DEFAULT NULL,
        `username` VARCHAR(60) NOT NULL DEFAULT '',
        `message` TEXT NULL,
        PRIMARY KEY (`id`),
        KEY `order_id` (`order_id`) ) ENGINE=MyISAM",
    "ALTER TABLE `{$_TABLES['paypal.products']}`
        ADD track_onhand TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
        ADD onhand INT(10) NOT NULL DEFAULT '0'",
);

$PP_UPGRADE['0.5.6'] = array(
    "ALTER TABLE {$_TABLES['paypal.products']}
        ADD oversell tinyint(1) not null default 0",
);

$PP_UPGRADE['0.5.7'] = array(
    "INSERT INTO {$_TABLES['blocks']}
        (type, name, title, tid, phpblockfn, is_enabled,
        owner_id, group_id,
        perm_owner, perm_group, perm_members, perm_anon)
    VALUES
        ('phpblock', 'paypal_recent', 'Newest Items', 'all',
            'phpblock_paypal_recent', 0, 2, 13, 3, 2, 2, 2)",
    "ALTER TABLE {$_TABLES['paypal.products']}
        CHANGE dt_add dt_add datetime not null,
        ADD `qty_discounts` text AFTER `oversell`,
        ADD `custom` varchar(255) NOT NULL DEFAULT ''",
    "UPDATE {$_TABLES['paypal.products']}
        SET dt_add = NOW()",
    "ALTER TABLE {$_TABLES['paypal.cart']}
        CHANGE last_update last_update datetime NOT NULL",
    "ALTER TABLE {$_TABLES['paypal.orders']}
        CHANGE last_mod last_mod datetime NOT NULL",
    "ALTER TABLE {$_TABLES['paypal.order_log']}
        CHANGE ts ts datetime NOT NULL",
    "ALTER TABLE {$_TABLES['paypal.categories']}
        CHANGE description description text",
    "TRUNCATE {$_TABLES['paypal.cart']}",
);

$PP_UPGRADE['0.5.8'] = array(
    "ALTER TABLE {$_TABLES['paypal.categories']}
        CHANGE group_id grp_access mediumint(8) unsigned not null default 13,
        DROP owner_id, DROP perm_owner, DROP perm_group, DROP perm_members, DROP perm_anon",
    "ALTER TABLE {$_TABLES['paypal.products']}
        DROP purch_grp,
        ADD sale_beg DATE DEFAULT '1900-01-01',
        ADD sale_end DATE DEFAULT '1900-01-01',
        ADD avail_beg DATE DEFAULT '1900-01-01',
        ADD avail_end DATE DEFAULT '9999-12-31'",
    "ALTER TABLE {$_TABLES['paypal.purchases']}
        ADD options_text text",
    "DELETE FROM {$_TABLES['paypal.gateways']} WHERE id='amazon'",
    "ALTER TABLE {$_TABLES['paypal.orders']}
        ADD buyer_email varchar(255) DEFAULT NULL AFTER phone",
    // Altered in 0.4.1 but installation sql wasn't updated
    "ALTER TABLE {$_TABLES['paypal.products']}
        CHANGE comments_enabled comments_enabled tinyint(1) default 0",
);

$PP_UPGRADE['0.5.9'] = array(
    // Fix the subgroup value, originally used the wrong field
    "UPDATE {$_TABLES['conf_values']} SET subgroup=10 where name='sg_shop'",
    "ALTER TABLE {$_TABLES['paypal.products']}
        CHANGE `name` `name` varchar(128) NOT NULL",
    "ALTER TABLE {$_TABLES['paypal.purchases']}
        CHANGE `product_id` `product_id` varchar(128) NOT NULL,
        CHANGE `txn_id` `txn_id` varchar(128) default ''",
    "ALTER TABLE {$_TABLES['paypal.images']}
        CHANGE `product_id` `product_id` int(11) unsigned NOT NULL",
    "ALTER TABLE {$_TABLES['paypal.categories']}
        CHANGE `cat_name` `cat_name` varchar(128) default ''",
    "INSERT INTO {$_TABLES['blocks']}
        (is_enabled, name, type, title, blockorder, phpblockfn)
        VALUES
        (0, 'paypal_search', 'phpblock', 'Search Catalog',
            9999, 'phpblock_paypal_search')",
);
$PP_UPGRADE['0.5.11'] = array(
    "ALTER TABLE {$_TABLES['paypal.address']}
        CHANGE `zip` `zip` varchar(40) DEFAUlT NULL,
        ADD KEY `uid` (`uid`,`zip`)",
    "ALTER TABLE {$_TABLES['paypal.orders']}
        CHANGE `billto_zip` `billto_zip` varchar(40) DEFAULT NULL,
        CHANGE `shipto_zip` `shipto_zip` varchar(40) DEFAULT NULL",
);
$PP_UPGRADE['0.6.0'] = array(
    // Drop new tables in case of a failed previous attempt.
    "DROP TABLE IF EXISTS {$_TABLES['paypal.sales']}",
    "DROP TABLE IF EXISTS {$_TABLES['paypal.coupons']}",
    "DROP TABLE IF EXISTS {$_TABLES['paypal.coupon_log']}",
    "ALTER TABLE {$_TABLES['paypal.purchases']} ADD extras text",
    "ALTER TABLE {$_TABLES['paypal.purchases']} ADD `shipping` decimal(9,4) NOT NULL DEFAULT '0.0000'",
    "ALTER TABLE {$_TABLES['paypal.purchases']} ADD `handling` decimal(9,4) NOT NULL DEFAULT '0.0000'",
    "ALTER TABLE {$_TABLES['paypal.purchases']} ADD `tax` decimal(9,4) NOT NULL DEFAULT '0.0000'",
    "ALTER TABLE {$_TABLES['paypal.purchases']} ADD taxable tinyint(1) unsigned NOT NULL DEFAULT '0' after `price`",
    "ALTER TABLE {$_TABLES['paypal.purchases']} CHANGE price price decimal(12,4) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD by_gc decimal(12,4) unsigned AFTER handling",
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD token varchar(20)",
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD tax_rate decimal(7,5) NOT NULL DEFAULT '0.00000'",
    "ALTER TABLE {$_TABLES['paypal.orders']} CHANGE shipping shipping decimal(9,4) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['paypal.orders']} CHANGE handling handling decimal(9,4) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['paypal.orders']} CHANGE tax tax decimal(9,4) NOT NULL DEFAULT 0",
    "CREATE TABLE `{$_TABLES['paypal.coupons']}` (
      `code` varchar(128) NOT NULL,
      `amount` decimal(12,4) unsigned NOT NULL DEFAULT '0.0000',
      `balance` decimal(12,4) unsigned NOT NULL DEFAULT '0.0000',
      `buyer` int(11) unsigned NOT NULL DEFAULT '0',
      `redeemer` int(11) unsigned NOT NULL DEFAULT '0',
      `purchased` int(11) unsigned NOT NULL DEFAULT '0',
      `redeemed` int(11) unsigned NOT NULL DEFAULT '0',
      `expires` date DEFAULT '9999-12-31',
      PRIMARY KEY (`code`),
      KEY `owner` (`redeemer`,`balance`,`expires`),
      KEY `purchased` (`purchased`)
    ) ENGINE=MyIsam",
    "CREATE TABLE {$_TABLES['paypal.coupon_log']} (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `uid` int(11) unsigned NOT NULL DEFAULT '0',
      `code` varchar(128) NOT NULL,
      `ts` int(11) unsigned DEFAULT NULL,
      `order_id` varchar(50) DEFAULT NULL,
      `amount` float(8,2) DEFAULT NULL,
      `msg` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `order_id` (`order_id`),
      KEY `code` (`code`)
    ) ENGINE=MyIsam",
    "ALTER TABLE {$_TABLES['paypal.buttons']} ADD `pi_name` varchar(20) NOT NULL DEFAULT 'paypal' FIRST",
    "ALTER TABLE {$_TABLES['paypal.buttons']} ADD `btn_key` varchar(20) AFTER gw_name",
    "ALTER TABLE {$_TABLES['paypal.buttons']} CHANGE item_id item_id varchar(40)",
    "ALTER TABLE {$_TABLES['paypal.buttons']} DROP PRIMARY KEY",
    "ALTER TABLE {$_TABLES['paypal.buttons']} ADD PRIMARY KEY (`pi_name`, `item_id`,`gw_name`,`btn_key`)",
    "CREATE TABLE {$_TABLES['paypal.sales']} (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(40),
      `item_type` varchar(10),
      `item_id` int(11) unsigned NOT NULL,
      `start` int(11) unsigned,
      `end` int(11) unsigned,
      `discount_type` varchar(10),
      `amount` float DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `item_type` (`item_type`,`item_id`,`start`,`end`)
    ) ENGINE=MyIsam",
    "ALTER TABLE {$_TABLES['paypal.products']} DROP comments",
    "ALTER TABLE {$_TABLES['paypal.products']} DROP sale_price",
    "ALTER TABLE {$_TABLES['paypal.products']} DROP sale_beg",
    "ALTER TABLE {$_TABLES['paypal.products']} DROP sale_end",
    "ALTER TABLE {$_TABLES['paypal.products']} CHANGE weight weight decimal(9,4) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['paypal.products']} CHANGE shipping_amt shipping_amt decimal(9,4) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['paypal.products']} CHANGE price price decimal(12,4) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['paypal.products']} ADD shipping_units decimal(9,4) NOT NULL DEFAULT 0 AFTER shipping_amt",
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD `info` text",
    "ALTER TABLE {$_TABLES['paypal.orders']} CHANGE last_mod last_mod timestamp",
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD `billto_id` int(11) unsigned NOT NULL DEFAULT '0'",
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD `shipto_id` int(11) unsigned NOT NULL DEFAULT '0'",
    "ALTER TABLE {$_TABLES['paypal.purchases']} DROP purchase_date",
    "ALTER TABLE {$_TABLES['paypal.purchases']} DROP user_id",
    "DROP TABLE IF EXISTS {$_TABLES['paypal.cart']}",
    "ALTER TABLE {$_TABLES['paypal.prod_attr']} CHANGE attr_price `attr_price` decimal(9,4) default '0.00'",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['paypal.shipping']}` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL DEFAULT '',
        `min_units` int(11) unsigned NOT NULL DEFAULT '0',
        `max_units` int(11) unsigned NOT NULL DEFAULT '0',
        `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
        `rates` text,
        PRIMARY KEY (`id`)
    ) ENGINE=MyIsam",
);
$PP_UPGRADE['0.6.1'] = array(
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD currency varchar(5) NOT NULL DEFAULT 'USD'",
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD order_seq int(11) UNSIGNED",
    "ALTER TABLE {$_TABLES['paypal.orders']} ADD UNIQUE (order_seq)",
    "SET @i:=0",
    "UPDATE {$_TABLES['paypal.orders']} SET order_seq = @i:=@i+1
        WHERE status NOT IN ('cart','pending') ORDER BY order_date ASC",
);

?>
