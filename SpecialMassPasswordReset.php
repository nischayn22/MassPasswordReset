<?php

use MediaWiki\MediaWikiServices;

/**
 *
 *
 */
class SpecialMassPasswordReset extends SpecialPage {
	public function __construct() {
		parent::__construct( 'MassPasswordReset', 'masspasswordreset' );
	}

	/**
	 * @inerhitDoc
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$request = $this->getRequest();
		$out = $this->getOutput();

		if ( !class_exists( "RandomLib\Factory" ) ) {
			$out->addHTML( '<div class="errorbox">You must install RandomLib. Run "composer require ircmaxell/random-lib" from your main directory.</div>' );
			return;
		}

		$ugm = MediaWikiServices::getInstance()->getUserGroupManager();
		if( !in_array( 'sysop', $ugm->getUserEffectiveGroups( $this->getUser() ) ) ) { // TODO: Replace me with $ugm->userHasRight()
			$out->addHTML( '<div class="errorbox">This page is only accessible by users with sysop right.</div>' );
			return;
		}

		$formOpts = [
			'id' => 'password_reset',
			'method' => 'post',
			"enctype" => "multipart/form-data",
			'action' => $this->getPageTitle()->getFullUrl()
		];
		$out->addHTML(
			Html::openElement( 'form', $formOpts ) . "<br>" .
			Html::label( "Upload CSV","", array( "for" => "password_reset_csv" ) ) . "<br>" .
			Html::element( 'input', array( "id" => "password_reset_csv", "name" => "password_reset_csv", "type" => "file" ) ) . "<br><br>" .
			Html::label( "New Password (Optional)", "", array( "for" => "new_password" ) ) . "<br>" .
			Html::element( 'input', array( "id" => "new_password", "name" => "new_password", "type" => "text" ) ) . "<br><br>"
		);
		$out->addHTML(
			Html::submitButton( "Submit", array() ) .
			Html::closeElement( 'form' )
		);

		if ( $request->getFileTempname( "password_reset_csv" ) ) {
			$this->handleUpload();
		}

	}

	public function handleUpload() {
		global $wgMassPasswordResetSender;

		$request = $this->getRequest();
		$out = $this->getOutput();

		$csv_array = array_map('str_getcsv', file( $request->getFileTempname( "password_reset_csv" ) ) );

		$password = $request->getVal( 'new_password' );

		if ( empty( $password ) ) {
			$factory = new RandomLib\Factory;
			$generator = $factory->getMediumStrengthGenerator();
			$password = $generator->generateString( 8 );
		}

		$updated_array = array();
		foreach( $csv_array as $csv_row ) {
			if ( empty( $csv_row[0] ) ) {
				continue;
			}
			$user = User::newFromName( $csv_row[0] );
			if ( !$user || !$user->getId() ) {
				$out->addHTML( 'No such user: ' . $csv_row[0] );
				return;
			}
			try {
				if ( !empty( $csv_row[1] ) ) {
					$user->setEmail( $csv_row[1] );
				}
				$status = $user->changeAuthenticationData( [
					'username' => $user->getName(),
					'password' => $password,
					'retype' => $password,
				] );
				if ( !$status->isGood() ) {
					$out->addHTML( $status->getWikiText( null, null, 'en' ) );
					return;
				}
				$user->saveSettings();
				$updated_array[] = array( $user->getName(), $user->getEmail(), $password );
			} catch ( PasswordError $pwe ) {
				$out->addHTML( $pwe->getText() );
				return;
			}
		}

		$out->addHTML( Html::element( 'h4', array(), "Results" ) );
		$out->addHTML( Html::openElement( 'table', array( "class" => "wikitable" ) ) );

		$out->addHTML( Html::openElement( 'tr' ) );
		$out->addHTML( Html::element( 'th', array(), "Username" ) );
		$out->addHTML( Html::element( 'th', array(), "Updated Email" ) );
		$out->addHTML( Html::element( 'th', array(), "Updated Password" ) );
		$out->addHTML( Html::element( 'th', array(), "Mail Status" ) );
		$out->addHTML( Html::closeElement( 'tr' ) );

		if ( is_null( $wgMassPasswordResetSender ) ) {
			$from = MailAddress::newFromUser( $this->getContext()->getUser() );
		} else {
			$from = new MailAddress( $wgMassPasswordResetSender );
		}

		foreach( $updated_array as $updated_row ) {
			$out->addHTML( Html::openElement( 'tr' ) );
			$out->addHTML( Html::element( 'td', array(), $updated_row[0] ) );
			$out->addHTML( Html::element( 'td', array(), $updated_row[1] ) );
			$out->addHTML( Html::element( 'td', array(), $updated_row[2] ) );

			$to = new MailAddress( $updated_row[1] );

			$text = wfMessage( 'masspasswordreset_email_body', $updated_row[0], $password, $this->getContext()->getUser() )->parse();

			$status = UserMailer::send( $to, $from, wfMessage( 'masspasswordreset_email_subject' )->parse(), $text, array( 'contentType' => 'text/html; charset=UTF-8' ) );

			if ( $status->isGood() ) {
				$out->addHTML( Html::element( 'td', array(), "Sent" ) );
			} else {
				$out->addHTML( Html::element( 'td', array(), "Failed" ) );
			}

			$out->addHTML( Html::closeElement( 'tr' ) );
		}
		$out->addHTML( Html::closeElement( 'table' ) );
	}
}
