<?php

namespace NetteAddons\Api;

use Nette\Utils\Json,
	NetteAddons\Model\Addon,
	NetteAddons\Model\Addons,
	NetteAddons\Model\Users,
	NetteAddons\Model\Facade\AddonManageFacade,
	NetteAddons\Model\Importers\GitHubImporter,
	NetteAddons\Model\Importers\RepositoryImporterManager;



/**
 * GitHub API
 *
 * @author Jan Dolecek <juzna.cz@gmail.com>
 * @author Patrik Votoček
 */
final class GithubPresenter extends \NetteAddons\BasePresenter
{
	/**
	 * @var \NetteAddons\Model\Facade\AddonManageFacade
	 * @inject
	 */
	public $manager;

	/**
	 * @var \NetteAddons\Model\Importers\RepositoryImporterManager
	 * @inject
	 */
	public $importerManager;

	/**
	 * @var \NetteAddons\Model\Users
	 * @inject
	 */
	public $users;

	/**
	 * @var \NetteAddons\Model\Addons
	 * @inject
	 */
	public $addons;



	/**
	 * Post receive hook, updates addon info
	 */
	public function actionPostReceive()
	{
		$post = $this->getRequest()->post;
		if (!isset($post['payload'], $post['username'], $post['apiToken'])) {
			$this->error('Invalid request.');
		}

		$response = $this->getHttpResponse();

		try {
			$payload = Json::decode($post['payload']);
			if (!isset($payload->repository->url)) {
				$response->setCode(400); // Bad Request
				$this->sendJson(array(
					'status' => 'error',
					'message' => 'Missing or invalid payload',
				));
			}
		} catch (\Nette\Utils\JsonException $e) {
			$this->error('Invalid request.');
		}

		$username = $post['username'];
		$token = $post['apiToken'];

		$user = $this->users->findOneByName($username);
		if (!$user || $user->apiToken !== $token) {
			$response->setCode(403); // Forbidden
			$this->sendJson(array(
				'status' => 'error',
				'message' => 'Invalid credentials'
			));
		}

		if (!GitHubImporter::isValid($payload->repository->url)) {
			$response->setCode(400); // Bad Request
			$this->sendJson(array(
				'status' => 'error',
				'message' => 'Could not parse payload repository URL'
			));
		}

		$repositoryUrl = GitHubImporter::normalizeUrl($payload->repository->url);
		$row = $this->addons->findOneBy(array('repository' => $repositoryUrl));
		if (!$row) {
			$this->error('Addon not found.');
		}
		$addon = Addon::fromActiveRow($row);

		$userIdentity = $this->users->createIdentity($user);
		$importer = $this->importerManager->createFromUrl($addon->repository);
		$this->manager->updateVersions($addon, $importer, $userIdentity);

		$this->sendJson(array('status' => "success"));
	}
}
