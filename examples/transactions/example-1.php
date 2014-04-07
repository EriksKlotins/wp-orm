<?php



/**
 * Šī transacija netiks izpildīta, jo ir Exception
 */
DB::transaction(function()
{
	throw new Exception('die');
	

	RequestAudit::create([
				'controller'	=>	'transaction',
				'action'		=>	'commit'
			]);

});


DB::beginTransaction();

RequestAudit::create([
				'controller'	=>	'transaction',
				'action'		=>	'commit'
			]);


DB::commit();
