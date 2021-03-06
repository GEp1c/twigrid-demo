<?php

use Nette\Forms\Form;


class FilterGrid extends BaseGrid
{

	/** @return void */
	protected function build()
	{
		parent::build();

		$this->setPrimaryKey('id');
		$this->addColumn('firstname', 'Name')->setSortable();
		$this->addColumn('surname', 'Surname')->setSortable();
		$this->addColumn('kilograms', 'Weight (kg)')->setSortable();

		$this->setFilterFactory([$this, 'filterFactory']);
		$this->setDataLoader([$this, 'dataLoader']);
	}


	/** @return Nette\Forms\Container */
	public function filterFactory()
	{
		$c = new Nette\Forms\Container;

		$c->addText('firstname');
		$c->addText('surname');

		$c->addText('kilograms')
			->addCondition(Form::FILLED)
				->addRule(Form::FLOAT);

		return $c;
	}


	/**
	 * @param  FilterGrid $grid
	 * @param  array $filters
	 * @param  array $order
	 * @return Nette\Database\Table\Selection
	 */
	public function dataLoader(FilterGrid $grid, array $filters, array $order)
	{
		$users = $this->database->table('user');

		// filtering
		foreach ($filters as $column => $value) {
			if ($column === 'kilograms') {
				$users->where("$column <= ?", $value);

			} else {
				$users->where("$column LIKE ?", "$value%");
			}
		}

		// sorting
		foreach ($order as $column => $dir) {
			$users->order($column . ($dir === TwiGrid\Components\Column::DESC ? ' DESC' : ''));
		}

		return $users->limit(12);
	}

}
