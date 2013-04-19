<?php


class RowActionGrid extends TwiGrid\DataGrid
{

	/** @var Nette\Database\Connection */
	protected $connection;



	function __construct(Nette\Http\Session $s, Nette\Database\Connection $connection)
	{
		parent::__construct($s);
		$this->connection = $connection;

		$this->setPrimaryKey('id');
		$this->addColumn('firstname', 'Name');
		$this->addColumn('surname', 'Surname');
		$this->addColumn('country_code', 'Country');
		$this->addColumn('birthday', 'Birthdate');

		$this->addRowAction('download', 'Download', $this->downloadItem)
			->setProtected(FALSE); // turns of the CSRF protection

		$this->addRowAction('delete', 'Delete', $this->deleteItem)
			->setConfirmation('Do you really want to delete this item?');

		$this->setDataLoader($this->dataLoader);
	}



	function dataLoader(RowActionGrid $grid, array $columns, array $filters, array $order)
	{
		return $this->connection->table('user')
			->select(implode(', ', $columns))
			->limit(12);
	}



	function downloadItem($id)
	{
		$this->flashMessage("Downloading item '$id'...", 'success');
	}



	function deleteItem($id)
	{
		$this->flashMessage("Deleting item '$id'...", 'success');
	}

}