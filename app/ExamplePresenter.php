<?php

use Nette\Forms\Form;


/** @persistent(dataGrid) */
class ExamplePresenter extends Nette\Application\UI\Presenter
{
	/** @var Nette\Database\Connection */
	protected $ndb;

	/** @var DibiConnection */
	protected $dibi;

	/** @var array */
	protected $actions = array(
		'ndb' => 'Nette\\Database',
		'dibi' => 'dibi',
	);

	/** @var Nette\Caching\Cache */
	protected $cache;

	const SCRIPT_KEY = 'grid-script-';



	/**
	 * @param  Nette\Database\Connection
	 * @param  DibiConnection
	 * @param  Nette\Caching\IStorage
	 * @return void
	 */
	function inject(Nette\Database\Connection $c, DibiConnection $d, Nette\Caching\IStorage $s)
	{
		$this->ndb = $c;
		$this->dibi = $d;
		$this->cache = new Nette\Caching\Cache($s, __CLASS__);

		$me = $this;
		$d->onEvent[] = function ($e) use ($me) { $me->logQuery( $e->sql ); };
		$c->onQuery[] = function ($s) use ($me) { $me->logQuery( $s->queryString ); };
	}



	/** @return void */
	protected function startup()
	{
		parent::startup();
		$this->payload->queries = array();
	}



	/**
	 * @param  string
	 * @return void
	 */
	function logQuery($sql)
	{
		$this->payload->queries[] = dibi::dump( $sql, TRUE );
	}



	/** @return void */
	protected function loadClientScripts()
	{
		foreach (array('js/twigrid.datagrid.js', 'css/twigrid.datagrid.css') as $file) {
			( ( $key = static::SCRIPT_KEY . $file ) && is_file( $dest = __DIR__ . '/../' . $file ) && $this->cache->load( $key ) ) || (
				copy($source = __DIR__ . '/../libs/TwiGrid/client-side/' . basename($file), $dest)
				&& $this->cache->save($key, TRUE, array(
					Nette\Caching\Cache::FILES => array($source),
				))
			);
		}
	}



	/**
	 * @param  string|NULL
	 * @return Nette\Templating\ITemplate
	 */
	protected function createTemplate($class = NULL)
	{
		$this->loadClientScripts();
		$this->invalidateControl('links');
		$this->invalidateControl('flashes');
		id ($template = parent::createTemplate($class))->actions = $this->actions;
		return $template
				->registerHelper('mtime', function ($f) { return $f . '?' . filemtime( __DIR__ . '/../' . $f ); })
				->setFile( __DIR__ . "/views/{$this->view}.latte" );
	}



	// === DATAGRID DEFINITION ==================================================================



	/** @return TwiGrid\DataGrid */
	protected function createComponentDataGrid()
	{
		$grid = $this->context->createDataGrid();
		$grid->setTemplateFile( __DIR__ . '/user-grid.latte' );

		$grid->addColumn('gender', 'Pohlaví');
		$grid->addColumn('name', 'Jméno')->setSortable();
		$grid->addColumn('country', 'Země');
		$grid->addColumn('emailaddress', 'E-mail');
		$grid->addColumn('birthday', 'Datum narození')->setSortable();
		$grid->addColumn('kilograms', 'Váha (kg)')->setSortable();
		$grid->addColumn('centimeters', 'Výška (cm)')->setSortable();

		$grid->setPrimaryKey( $this->ndb->table('user')->primary );
		$grid->setFilterContainerFactory( $this->createFilterContainer );
		$grid->setDataLoader( $this->{ $this->view . 'DataLoader' } );
		$grid->setRecordValueGetter( $this->{ $this->view . 'RecordValueGetter' } );
		$grid->setTimelineBehavior();

		$grid->setDefaultOrderBy('name');

		$minmax = $this->{ $this->view . 'LoadMinMaxBirthday' }();
		foreach ($minmax as $n => $foo) { $minmax[ $n ] = id( new DateTime($minmax[ $n ]) )->format('Y-m-d'); }

		$grid->setDefaultFilters(array(
			'birthday' => $minmax,
		));

		$grid->addRowAction('edit', 'Upravit', $this->editRecord);
		$grid->addRowAction('delete', 'Smazat', $this->deleteRecord, 'Opravdu chcete smazat tento záznam?');
		$grid->addGroupAction('change', 'Změnit záznamy', $this->manipulateGroup, 'Opravdu chcete změnit vybrané položky?');

		return $grid;
	}



	/** @return Nette\Forms\Container */
	function createFilterContainer()
	{
		$container = new Nette\Forms\Container;

		$container->addSelect('gender', 'Pohlaví', array(
			'male' => 'Muž',
			'female' => 'Žena',
		))->setPrompt('---');

		$container->addText('name');
		$container->addText('emailaddress');

		// simple date form control
		$addDate = function ($c, $n) {
			$r = $c->addText($n);
			$r->addCondition( Form::FILLED )->addRule( function ($control) {
				try {
					new DateTime($control->value);
					return TRUE;
				} catch (Exception $e) {}
				return FALSE;
			}, 'Datum prosím zadávejte ve formátu YYYY-MM-DD' );
			return $r;
		};

		$birthday = $container->addContainer('birthday');
		$min = $addDate( $birthday, 'min' );
		$max = $addDate( $birthday, 'max' );

		$min->addCondition( Form::FILLED )->addRule( function () use ($min, $max) {
			return !$max->filled || id( new DateTime($min->value) ) <= id( new DateTime($max->value) );
		}, 'Minimální datum nesmí následovat po maximálním.' );

		$container->addSelect( 'country', 'Země')
				->setItems( $this->{ $this->view . 'LoadCountries' }(), FALSE )
				->setPrompt('---');

		$container->addText('kilograms')->addCondition( Form::FILLED )->addRule( Form::FLOAT );
		$container->addText('centimeters')->addCondition( Form::FILLED )->addRule( Form::INTEGER );

		return $container;
	}



	// === NETTE\DATABASE ==================================================================



	/** @return array */
	protected function ndbLoadCountries()
	{
		$key = __METHOD__;
		$countries = $this->cache->load($key);
		return $countries !== NULL ? $countries : $this->cache->save( $key, $this->ndb->table('country')
				->select( '('
					. $this->ndb->table('user')
						->select('id')
						->where('country_code = code')
						->limit(1)
						->getSql()
				. ') AS is_used, code, title')
				->where('is_used IS NOT NULL')
				->fetchPairs('code', 'title') );
	}



	/** @return array */
	protected function ndbLoadMinMaxBirthday()
	{
		$key = __METHOD__;
		$countries = $this->cache->load($key);
		return $countries !== NULL ? $countries : $this->cache->save( $key, $this->ndb->table('user')
				->select('MIN(birthday) AS min, MAX(birthday) AS max')
				->fetch()->toArray() );
	}



	/**
	 * @param  TwiGrid\DataGrid
	 * @param  array
	 * @param  array column => desc?
	 * @param  array
	 * @param  int
	 * @return Nette\Database\Table\Selection
	 */
	function ndbDataLoader(TwiGrid\DataGrid $grid, array $columns, array $orderBy, array $filters, $page)
	{
		// selection factory
		$users = $this->ndb->table('user');

		// columns
		unset($columns['country']);
		$columns[] = 'country_code';
		$columns['name'] = 'surname || " " || firstname AS name';
		$users->select( implode(', ', $columns) );

		// order result
		foreach ($orderBy as $column => $desc) {
			$users->order( $column . ($desc ? ' DESC' : '') );
		}

		// filter result
		$conds = array();
		foreach ($filters as $column => $value) {
			if ($column === 'gender') {
				$conds[ $column ] = $value;

			} elseif ($column === 'country') {
				$conds[ "(SELECT title FROM country WHERE code = country_code) = ?" ] = $value;

			} elseif ($column === 'birthday') {
				isset($value['min']) && $conds["$column >= ?"] = $value['min'];
				isset($value['max']) && $conds["$column <= ?"] = $value['max'];

			} elseif ($column === 'centimeters') {
				$conds["$column <= ?"] = $value;

			} elseif ($column === 'kilograms') {
				$conds["$column <= ?"] = $value;

			} else {
				$conds["$column LIKE ?"] = "%$value%";
			}
		}

		$max = 72;
		$grid->setCountAll( min($max, id(clone $users->where($conds))->select('COUNT(*) AS count_all')->limit(1)->fetch()->{'count_all'}) );
		return $users->limit( $page === -1 ? $max : min($max, $page * 16) );
	}



	/**
	 * @param  Nette\Database\Table\ActiveRow
	 * @param  string
	 * @return mixed
	 */
	function ndbRecordValueGetter(Nette\Database\Table\ActiveRow $record, $column)
	{
		return $column === 'country' ? $record->ref('country', 'country_code')->title : $record->$column;
	}



	// === DIBI ==================================================================



	/** @return array */
	protected function dibiLoadCountries()
	{
		$key = __METHOD__;
		$countries = $this->cache->load($key);
		return $countries !== NULL ? $countries : $this->cache->save( $key, $this->dibi->select('[code], [title]')
				->select(
					$this->dibi->select('[id]')
						->from('[user]')
						->where('[country_code] = [code]')
						->limit(1)
				)->as('[is_used]')
				->from('[country]')
				->where('[is_used] IS NOT NULL')
				->fetchPairs('code', 'title') );
	}



	/** @return array */
	protected function dibiLoadMinMaxBirthday()
	{
		$key = __METHOD__;
		$countries = $this->cache->load($key);
		return $countries !== NULL ? $countries : $this->cache->save( $key, (array) $this->dibi->select('MIN([birthday])')->as('[min]')
				->select('MAX([birthday])')->as('[max]')
				->from('[user]')->fetch() );
	}



	/**
	 * @param  TwiGrid\DataGrid
	 * @param  array
	 * @param  array column => desc?
	 * @param  array
	 * @param  int
	 * @return array
	 */
	function dibiDataLoader(TwiGrid\DataGrid $grid, array $columns, array $orderBy, array $filters, $page)
	{
		// columns
		unset($columns['name'], $columns['country']);
		$users = $this->dibi
				->select( array_values($columns) )
				->select('[surname] || " " || [firstname]')->as('[name]')
				->select('[country].[title] AS [country]')
				->from('[user]')
				->innerJoin('[country]')->on('[user].[country_code] = [country].[code]');

		// order result
		foreach ($orderBy as $column => $desc) {
			$users->orderBy( $column . ($desc ? ' DESC' : '') );
		}

		// filter result
		$conds = array();
		foreach ($filters as $column => $value) {
			if ($column === 'gender' || $column === 'country') {
				$conds[ $column ] = $value;

			} elseif ($column === 'birthday') {
				isset($value['min']) && $conds[] = array("[$column] >= %d", $value['min']);
				isset($value['max']) && $conds[] = array("[$column] <= %d", $value['max']);

			} elseif ($column === 'centimeters') {
				$conds[] = array("$column <= %i", $value);

			} elseif ($column === 'kilograms') {
				$conds[] = array("[$column] <= %f", $value);

			} else {
				$conds[] = array("[$column] LIKE %s", "%$value%");
			}
		}

		$max = 72;
		$grid->setCountAll( min($max, id(clone $users->where($conds))->select('COUNT(*)')->as('[count_all]')->fetch()->{'count_all'}) );
		return $users->limit( $page === -1 ? $max : min($max, $page * 16) )->fetchAll();
	}



	/**
	 * @param  DibiRow
	 * @param  string
	 * @return mixed
	 */
	function dibiRecordValueGetter(DibiRow $record, $column)
	{
		return $record->$column;
	}



	// === DATA MANIPULATIONS ===============================================================



	/**
	 * @param  int
	 * @return void
	 */
	function editRecord($id)
	{
		$this->getRecord($id);
		$this->flashMessage( "Požadavek na změnu záznamu s ID '$id'.", 'success' );
		!$this->isAjax() && $this->redirect('this');
	}



	/**
	 * @param  int
	 * @return void
	 */
	function deleteRecord($id)
	{
		$this->getRecord($id);
		$this->flashMessage( "Požadavek na smazání záznamu s ID '$id'.", 'warning' );
		!$this->isAjax() && $this->redirect('this');
	}



	/**
	 * @param  int
	 * @return mixed
	 */
	protected function getRecord($id)
	{
		return $this->view === 'ndb'
				? $this->ndb->table('user')->get($id)
				: $this->dibi->select('*')->from('user')->where('[id] = %i', $id)->fetch();
	}



	/**
	 * @param  array
	 * @return void
	 */
	function manipulateGroup(array $primaries)
	{
		$this->view === 'ndb'
				? $this->ndb->table('user')->where('id', $primaries)->fetchPairs('id')
				: $this->dibi->select('*')->from('user')->where('[id] IN %in', $primaries)->fetchAssoc('id');

		$this->flashMessage( "Požadavek na změnu záznamů s ID: " . Nette\Utils\Json::encode($primaries), 'success' );
		!$this->isAjax() && $this->redirect('this');
	}
}
