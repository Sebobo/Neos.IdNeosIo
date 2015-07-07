<?php
namespace Neos\IdNeosIo\Controller;

/*                                                          *
 * This script belongs to the Flow package "Neos.IdNeosIo". *
 *                                                          */

use Flownative\DoubleOptIn\Token;
use Flownative\DoubleOptIn\Helper;
use Neos\CrowdClient\Domain\Service\CrowdClient;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Security\Authentication\TokenInterface;

class ResetPasswordController extends \TYPO3\Flow\Mvc\Controller\ActionController {

	/**
	 * @var string $crowdApplicationName
	 * @Flow\Inject(setting="crowdApplicationName")
	 */
	protected $crowdApplicationName;

	/**
	 * @var string $crowdApplicationPassword
	 * @Flow\Inject(setting="crowdApplicationPassword")
	 */
	protected $crowdApplicationPassword;

	/**
	 * @var string $authenticationProviderName
	 * @Flow\Inject(setting="authenticationProviderName")
	 */
	protected $authenticationProviderName;

	/**
	 * @var CrowdClient
	 */
	protected $crowdClient;

	/**
	 * @var Helper
	 * @Flow\Inject
	 */
	protected $doubleOptInHelper;

	/**
	 * @var \TYPO3\Flow\Security\AccountRepository
	 * @Flow\Inject
	 */
	protected $accountRepository;

	/**
	 * @var \TYPO3\Flow\Security\Context
	 * @Flow\Inject
	 */
	protected $securityContext;

	/**
	 * @return void
	 */
	public function initializeObject() {
		$this->crowdClient = new CrowdClient($this->crowdApplicationName, $this->crowdApplicationPassword);
	}

	/**
	 * @return void
	 */
	public function indexAction() {
	}

	/**
	 * @param string $username
	 * @return void
	 */
	public function sendResetLinkAction($username) {
		$userData = $this->crowdClient->getUser($username);
		if ($userData !== NULL) {
			$token = $this->doubleOptInHelper->generateToken($userData['email'], 'id.neos.io reset password', $userData);
			$this->doubleOptInHelper->setRequest($this->request);
			$this->doubleOptInHelper->sendActivationMail($userData['email'], $token);

			$this->redirect('resetPasswordMailSent');

		}
		//TODO: error handling
	}


	/**
	 * @return void
	 */
	public function resetPasswordMailSentAction() {
	}

	/**
	 * @param Token $token
	 * @return void
	 */
	public function onetimeLoginAction(Token $token) {
		$username = $token->getMeta()['name'];

		/** @var $account \TYPO3\Flow\Security\Account */
		$account = NULL;
		$providerName = $this->authenticationProviderName;
		$accountRepository = $this->accountRepository;
		$this->securityContext->withoutAuthorizationChecks(function() use ($username, $providerName, $accountRepository, &$account) {
			$account = $accountRepository->findActiveByAccountIdentifierAndAuthenticationProviderName($username, $providerName);
		});

		foreach ($this->securityContext->getAuthenticationTokens() as $authenticationToken) {
			if ($authenticationToken->getAuthenticationProviderName() === $providerName) {
				$authenticationToken->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);
				$authenticationToken->setAccount($account);
				break;
			}
		}

		$this->redirect('resetForm');
	}

	/**
	 * @return void
	 */
	public function resetFormAction() {
	}

	/**
	 * @param string $password
	 * @param string $passwordConfirmation
	 * @return void
	 */
	public function resetAction($password, $passwordConfirmation) {
		if ($password === '' || $password !== $passwordConfirmation) {
			$this->flashMessageContainer->addMessage(new \TYPO3\Flow\Error\Error('Passwords didn\'t match!', 1435750717));
			return $this->errorAction();
		}
		$this->crowdClient->setPasswordForUser($this->securityContext->getAccount()->getAccountIdentifier(), $password);
		$this->addFlashMessage('Your password has been updated!');
		$this->redirect('resetForm');
	}

	/**
	 * A template method for displaying custom error flash messages, or to
	 * display no flash message at all on errors. Override this to customize
	 * the flash message in your action controller.
	 *
	 * @return \TYPO3\Flow\Error\Message The flash message or FALSE if no flash message should be set
	 * @api
	 */
	protected function getErrorFlashMessage() {
		return FALSE;
	}
}