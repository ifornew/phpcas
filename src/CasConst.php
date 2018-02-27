<?php

namespace Iwannamaybe\PhpCas;

class CasConst
{
	const CAS_VERSION_1_0 = '1.0';
	const CAS_VERSION_2_0 = '2.0';
	const CAS_VERSION_3_0 = '3.0';

	/**
	 * Constants used for determining rebroadcast type (logout or pgtIou/pgtId).
	 */
	const LOGOUT = 0;
	const PGTIOU = 1;

	/**
	 * SAML protocol
	 */
	const SAML_VERSION_1_1 = 'S1';

	/**
	 * XML header for SAML POST
	 */
	const SAML_XML_HEADER = '<?xml version="1.0" encoding="UTF-8"?>';

	/**
	 * SOAP envelope for SAML POST
	 */
	const SAML_SOAP_ENV = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Header/>';

	/**
	 * SOAP body for SAML POST
	 */
	const SAML_SOAP_BODY = '<SOAP-ENV:Body>';

	/**
	 * SAMLP request
	 */
	const SAMLP_REQUEST = '<samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"  MajorVersion="1" MinorVersion="1" RequestID="_192.168.16.51.1024506224022" IssueInstant="2002-06-19T17:03:44.022Z">';

	const SAMLP_REQUEST_CLOSE = '</samlp:Request>';

	/**
	 * SAMLP artifact tag (for the ticket)
	 */
	const SAML_ASSERTION_ARTIFACT = '<samlp:AssertionArtifact>';

	/**
	 * SAMLP close
	 */
	const SAML_ASSERTION_ARTIFACT_CLOSE = '</samlp:AssertionArtifact>';

	/**
	 * SOAP body close
	 */
	const SAML_SOAP_BODY_CLOSE = '</SOAP-ENV:Body>';

	/**
	 * SOAP envelope close
	 */
	const SAML_SOAP_ENV_CLOSE = '</SOAP-ENV:Envelope>';

	/**
	 * SAML Attributes
	 */
	const SAML_ATTRIBUTES = 'SAMLATTRIBS';

	/**
	 * SAML Attributes
	 */
	const DEFAULT_ERROR = 'Internal script failure';
}