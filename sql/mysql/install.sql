INSERT IGNORE INTO `#__rsform_config` (`SettingName`, `SettingValue`) VALUES
('raypay.user_id', ''),
('raypay.marketing_id', ''),
('raypay.sandbox', ''),
('raypay.currency', '');

INSERT IGNORE INTO `#__rsform_component_types` (`ComponentTypeId`, `ComponentTypeName`) VALUES (800, 'raypay');

DELETE FROM `#__rsform_component_type_fields` WHERE ComponentTypeId = 800;

INSERT IGNORE INTO `#__rsform_component_type_fields` (`ComponentTypeId`, `FieldName`, `FieldType`, `FieldValues`,`Properties`,`Ordering`) VALUES
(800, 'NAME', 'textbox','','', 0),
(800, 'LABEL', 'textbox','','', 1),
(800, 'TOTAL', 'select', 'YES\r\nNO', '{"case":{"YES":{"show":[],"hide":["FIELDNAME"]},"NO":{"show":["FIELDNAME"],"hide":[]}}}',3 ),
(800, 'FIELDNAME', 'select','//<code>\r\n $a="Select the desired field"; foreach (RSFormProHelper::getComponents($_GET["formId"]) as $item) { if ($item->ComponentTypeId == 21 or $item->ComponentTypeId == 22 or $item->ComponentTypeId == 28 or $item->ComponentTypeId == 23){  $a= $a . "\r\n" . $item->name; } } return $a;   \r\n//</code>','', 4),
(800, 'COMPONENTTYPE', 'hidden', '800','', 5),
(800, 'LAYOUTHIDDEN', 'hiddenparam', 'YES','', 7);
