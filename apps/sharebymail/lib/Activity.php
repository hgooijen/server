<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\ShareByMail;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Activity implements IProvider {

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	/** @var IUserManager */
	protected $userManager;
	/** @var IContactsManager */
	protected $contactsManager;

	/** @var array */
	protected $displayNames = [];

	/** @var array */
	protected $contactNames = [];

	const SUBJECT_SHARED_EMAIL_SELF = 'shared_with_email_self';
	const SUBJECT_SHARED_EMAIL_BY = 'shared_with_email_by';
	const SUBJECT_SHARED_EMAIL_PASSWORD_SEND = 'shared_with_email_password_send';
	const SUBJECT_SHARED_EMAIL_PASSWORD_SEND_SELF = 'shared_with_email_password_send_self';

	/**
	 * @param IFactory $languageFactory
	 * @param IURLGenerator $url
	 * @param IManager $activityManager
	 * @param IUserManager $userManager
	 * @param IContactsManager $contactsManager
	 */
	public function __construct(IFactory $languageFactory, IURLGenerator $url, IManager $activityManager, IUserManager $userManager, IContactsManager $contactsManager) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->userManager = $userManager;
		$this->contactsManager = $contactsManager;
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'sharebymail') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('sharebymail', $language);

		if ($this->activityManager->isFormattingFilteredObject()) {
			try {
				return $this->parseShortVersion($event);
			} catch (\InvalidArgumentException $e) {
				// Ignore and simply use the long version...
			}
		}

		return $this->parseLongVersion($event);
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);

		if ($event->getSubject() === self::SUBJECT_SHARED_EMAIL_SELF) {
			$event->setParsedSubject($this->l->t('Shared with %1$s', [
					$parsedParameters['email']['name'],
				]))
				->setRichSubject($this->l->t('Shared with {email}'), [
					'email' => $parsedParameters['email'],
				])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		} else if ($event->getSubject() === self::SUBJECT_SHARED_EMAIL_BY) {
			$event->setParsedSubject($this->l->t('Shared with %1$s by %2$s', [
				$parsedParameters['email']['name'],
				$parsedParameters['actor']['name'],
			]))
				->setRichSubject($this->l->t('Shared with {email} by {actor}'), [
					'email' => $parsedParameters['email'],
					'actor' => $parsedParameters['actor'],
				])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		} else if ($event->getSubject() === self::SUBJECT_SHARED_EMAIL_PASSWORD_SEND) {
			$event->setParsedSubject($this->l->t('Password for mail share sent to %1$s', [
				$parsedParameters['email']['name']
			]))
				->setRichSubject($this->l->t('Password for mail share sent to {email}'), [
					'email' => $parsedParameters['email']
				])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		} else if ($event->getSubject() === self::SUBJECT_SHARED_EMAIL_PASSWORD_SEND_SELF) {
			$event->setParsedSubject($this->l->t('Password for mail share sent to you'))
				->setRichSubject($this->l->t('Password for mail share sent to you'))
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		} else {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseLongVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event);

		if ($event->getSubject() === self::SUBJECT_SHARED_EMAIL_SELF) {
			$event->setParsedSubject($this->l->t('You shared %1$s with %2$s by mail', [
					$parsedParameters['file']['path'],
					$parsedParameters['email']['name'],
				]))
				->setRichSubject($this->l->t('You shared {file} with {email} by mail'), $parsedParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		} else if ($event->getSubject() === self::SUBJECT_SHARED_EMAIL_BY) {
			$event->setParsedSubject($this->l->t('%3$s shared %1$s with %2$s by mail', [
				$parsedParameters['file']['path'],
				$parsedParameters['email']['name'],
				$parsedParameters['actor']['name'],
			]))
				->setRichSubject($this->l->t('{actor} shared {file} with {email} by mail'), $parsedParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		} else if ($event->getSubject() === self::SUBJECT_SHARED_EMAIL_PASSWORD_SEND) {
			$event->setParsedSubject($this->l->t('Password to access %1$s was send to %2s', [
				$parsedParameters['file']['path'],
				$parsedParameters['email']['name']
			]))
				->setRichSubject($this->l->t('Password to access {file} was send to {email}'), $parsedParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));
		} else if ($event->getSubject() === self::SUBJECT_SHARED_EMAIL_PASSWORD_SEND_SELF) {
			$event->setParsedSubject(
				$this->l->t('Password to access %1$s was send to you',
					[$parsedParameters['file']['path']]))
				->setRichSubject($this->l->t('Password to access {file} was send to you'), $parsedParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));

		} else {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	protected function getParsedParameters(IEvent $event) {
		$subject = $event->getSubject();
		$parameters = $event->getSubjectParameters();

		switch ($subject) {
			case self::SUBJECT_SHARED_EMAIL_SELF:
				return [
					'file' => $this->generateFileParameter((int) $event->getObjectId(), $parameters[0]),
					'email' => $this->generateEmailParameter($parameters[1]),
				];
			case self::SUBJECT_SHARED_EMAIL_BY:
				return [
					'file' => $this->generateFileParameter((int) $event->getObjectId(), $parameters[0]),
					'email' => $this->generateEmailParameter($parameters[1]),
					'actor' => $this->generateUserParameter($parameters[2]),
				];
			case self::SUBJECT_SHARED_EMAIL_PASSWORD_SEND:
				return [
					'file' => $this->generateFileParameter((int) $event->getObjectId(), $parameters[0]),
					'email' => $this->generateEmailParameter($parameters[1]),
				];
			case self::SUBJECT_SHARED_EMAIL_PASSWORD_SEND_SELF:
				return [
					'file' => $this->generateFileParameter((int) $event->getObjectId(), $parameters[0]),
				];
		}
		throw new \InvalidArgumentException();
	}

	/**
	 * @param int $id
	 * @param string $path
	 * @return array
	 */
	protected function generateFileParameter($id, $path) {
		return [
			'type' => 'file',
			'id' => $id,
			'name' => basename($path),
			'path' => trim($path, '/'),
			'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $id]),
		];
	}

	/**
	 * @param string $email
	 * @return array
	 */
	protected function generateEmailParameter($email) {
		if (!isset($this->contactNames[$email])) {
			$this->contactNames[$email] = $this->getContactName($email);
		}

		return [
			'type' => 'email',
			'id' => $email,
			'name' => $this->contactNames[$email],
		];
	}

	/**
	 * @param string $uid
	 * @return array
	 */
	protected function generateUserParameter($uid) {
		if (!isset($this->displayNames[$uid])) {
			$this->displayNames[$uid] = $this->getDisplayName($uid);
		}

		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->displayNames[$uid],
		];
	}

	/**
	 * @param string $email
	 * @return string
	 */
	protected function getContactName($email) {
		$addressBookContacts = $this->contactsManager->search($email, ['EMAIL']);

		foreach ($addressBookContacts as $contact) {
			if (isset($contact['isLocalSystemBook'])) {
				continue;
			}

			if (in_array($email, $contact['EMAIL'])) {
				return $contact['FN'];
			}
		}

		return $email;
	}

	/**
	 * @param string $uid
	 * @return string
	 */
	protected function getDisplayName($uid) {
		$user = $this->userManager->get($uid);
		if ($user instanceof IUser) {
			return $user->getDisplayName();
		} else {
			return $uid;
		}
	}
}
