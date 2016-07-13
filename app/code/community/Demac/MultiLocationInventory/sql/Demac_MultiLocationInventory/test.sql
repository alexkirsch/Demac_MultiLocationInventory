UPDATE `cataloginventory_stock_status` AS `dest`
  INNER JOIN `demac_mli_stock_indexer` AS `src` ON dest.product_id = src.product_id AND dest.website_id = src.website_id
SET `dest`.`qty` = `src`.`qty`, `dest`.`stock_status` = `src`.`is_in_stock`
WHERE (src.product_id IN (''))
