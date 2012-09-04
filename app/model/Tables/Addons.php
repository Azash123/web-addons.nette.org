<?php

namespace NetteAddons\Model;

use NetteAddons;
use Nette;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\DateTime;
use Nette\Http;



/**
 * Addons table repository
 */
class Addons extends Table
{
	/** @var string */
	protected $tableName = 'addons';

	/** @var AddonVersions versions repository */
	private $versions;

	/** @var Tags tags repository */
	private $tags;



	public function __construct(Nette\Database\Connection $dbConn, AddonVersions $versions, Tags $tags)
	{
		parent::__construct($dbConn);
		$this->versions = $versions;
		$this->tags = $tags;
	}




// === Selecting addons ========================================================

	/**
	 * Filter addons selection by tag.
	 *
	 * @param  \Nette\Database\Table\Selection
	 * @param  int tag id
	 * @return \Nette\Database\Table\Selection for fluent interface
	 */
	public function filterByTag(Selection $addons, $tagId)
	{
		$addonIds = $this->connection->table('addons_tags')
			->where('tagId = ?', $tagId)->select('addonId');

		return $addons->where('id', $addonIds);
	}



	/**
	 * Filter addon selection by some text.
	 *
	 * @param  \Nette\Database\Table\Selection
	 * @param  string
	 * @return \Nette\Database\Table\Selection for fluent interface
	 */
	public function filterByString(Selection $addons, $string)
	{
		$string = "%$string%";
		return $addons->where('name LIKE ? OR shortDescription LIKE ?', $string, $string);
	}



// === CRUD ====================================================================

	/**
	 * Saves addon to database.
	 *
	 * @author Jan Tvrdík
	 * @param  Addon
	 * @return ActiveRow created row
	 * @throws \NetteAddons\DuplicateEntryException
	 * @throws \NetteAddons\InvalidArgumentException
	 * @throws \PDOException
	 */
	public function add(Addon $addon)
	{
		if ($addon->id !== NULL) {
			throw new \NetteAddons\InvalidArgumentException('Addon already has an ID.');
		}

		if (count($addon->versions) < 1) {
			throw new \NetteAddons\InvalidArgumentException('Addon must have at least one version.');
		}

		$this->connection->beginTransaction();
		try {
			$row = $this->createRow(array(
				'name'             => $addon->name,
				'composerName'     => $addon->composerName,
				'userId'           => $addon->userId,
				'repository'       => $addon->repository,
				'shortDescription' => $addon->shortDescription,
				'description'      => $addon->description,
				'demo'             => $addon->demo ?: NULL,
				'defaultLicense'   => $addon->defaultLicense,
				'updatedAt'        => new Datetime('now'),
			));

			$addon->id = $row->id;
			foreach ($addon->versions as $version) {
				$this->versions->add($version);
			}

			foreach ($addon->tags as $tag) {
				$this->tags->addAddonTag($row, $tag);
			}

			$this->connection->commit();
			return $row;

		} catch (\Exception $e) {
			$this->connection->rollBack();
			$addon->id = NULL;
			throw $e;
		}
	}



	public function update(Addon $addon)
	{
		// this may fail, becase find() may return FALSE
		$this->find($addon->id)->update(array(
			'name'             => $addon->name,
			'repository'       => $addon->repository,
			'shortDescription' => $addon->shortDescription,
			'description'      => $addon->description,
			'demo'             => $addon->demo ?: NULL,
			'defaultLicense'   => $addon->defaultLicense,
			'updatedAt'        => new Datetime('now'),
		));
	}
}