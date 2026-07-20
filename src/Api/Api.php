<?php
namespace Akki\SyliusPayumSlimpayPlugin\Api;

use Akki\SyliusPayumSlimpayPlugin\Constants\Constants;
use Exception;
use HapiClient\Exception\LinkNotUniqueException;
use HapiClient\Exception\RelNotFoundException;
use HapiClient\Hal\Resource;
use HapiClient\Http\Follow;
use HapiClient\Http\JsonBody;
use Http\Message\MessageFactory;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\HttpClientInterface;
use HapiClient\Http\HapiClient;
use HapiClient\Http\Auth\Oauth2BasicAuthentication;
use HapiClient\Hal\CustomRel;

class Api
{
    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var HapiClient
     */
    protected $hapiClient;

    /**
     * @param array               $options
     * @param HttpClientInterface $client
     * @param MessageFactory      $messageFactory
     *
     * @throws InvalidArgumentException if an option is invalid
     */
    public function __construct(array $options, HttpClientInterface $client, MessageFactory $messageFactory)
    {
        $this->options = $options;
        $this->client = $client;
        $this->messageFactory = $messageFactory;
        $this->hapiClient = new HapiClient(
            $this->getApiEndpoint(),
            '/',
            Constants::PROFILE_URI . '/alps/v1',
            new Oauth2BasicAuthentication(
                '/oauth/token',
                $options['app_id'],
                $options['app_secret']
            )
        );
    }

    /**
     * @param string $subscriberReference
     * @param string $paymentSchema
     * @param array $mandateFields
     *
     * @param string $returnUrl
     * @param int $amount
     * @param string $currency
     * @param string|null $mandateReference
     *
     * @return Resource
     * @throws Exception
     */
    public function signMandate($subscriberReference, $paymentSchema, array $mandateFields, string $returnUrl, int $amount = 0, string $currency = '', ?string $mandateReference = null)
    {
        $fields = [
            'started' => true,
            'locale' => null,
            'paymentScheme' => $paymentSchema,
            'returnUrl' => $returnUrl,
            'creditor' => [
                'reference' => $this->options['creditor_reference']
            ],
            'subscriber' => [
                'reference' => $subscriberReference
            ],
            'items' => [
                [
                    'type' => Constants::ITEM_TYPE_SIGN_MANDATE,
                    'action' => Constants::ITEM_ACTION_SIGN,
                    'mandate' => [
//                        'reference' => $mandateReference,
                        'signatory' => $mandateFields
                    ]
                ]
            ]
        ];

        if ($amount > 0)
        {
            $fields['items'][] = [
                'type' => Constants::ITEM_TYPE_PAYMENT,
                'action' => Constants::ITEM_ACTION_CREATE,
                'payin' => [
                    'reference' => "{$mandateReference}_PREL",
                    'amount' => $amount/100,
                    'currency' => $currency,
                    'scheme' => null,
                    'label' => 'Premier prélèvement',
                    'executionDate' => null
                ]
            ];
        }

        return $this->doRequest('POST', Constants::FOLLOW_CREATE_ORDERS, $fields);
    }

    /**
     * @param string $subscriberReference
     * @param string $mandateReference
     *
     * @return Resource
     * @throws Exception
     */
    public function updatePaymentMethodWithCheckout($subscriberReference, $mandateReference)
    {
        $fields = [
            'started' => true,
            'locale' => null,
            'creditor' => [
                'reference' => $this->options['creditor_reference']
            ],
            'subscriber' => [
                'reference' => $subscriberReference
            ],
            'items' => [
                [
                    'type' => Constants::ITEM_TYPE_SIGN_MANDATE,
                    'action' => Constants::ITEM_ACTION_AMEND_BANK_ACCOUNT,
                    'mandate' => [
                        'reference' => $mandateReference
                    ]
                ]
            ]
        ];

        return $this->doRequest('POST', Constants::FOLLOW_CREATE_ORDERS, $fields);
    }

    /**
     * @param string $mandateReference
     * @param string $iban
     *
     * @return Resource
     * @throws Exception
     */
    public function updatePaymentMethodWithIban($mandateReference, $iban)
    {
        $mandate = $this->doRequest('GET', Constants::FOLLOW_GET_MANDATES, [
            'creditorReference' => $this->options['creditor_reference'],
            'reference' => $mandateReference
        ]);

        return $this->doRequest('POST', Constants::FOLLOW_UPDATE_BANK_ACCOUNT, [
            'iban' => $iban
        ], $mandate);
    }

    /**
     * @param string $subscriberReference
     *
     * @return Resource
     * @throws Exception
     */
    public function setUpCardAlias($subscriberReference)
    {
        $fields = [
            'started' => true,
            'locale' => null,
            'paymentScheme' => Constants::PAYMENT_SCHEME_CARD,
            'creditor' => [
                'reference' => $this->options['creditor_reference']
            ],
            'subscriber' => [
                'reference' => $subscriberReference
            ],
            'items' => [
                [
                    'type' => Constants::ITEM_TYPE_CARD_ALIAS,
                ]
            ]
        ];

        return $this->doRequest('POST', Constants::FOLLOW_CREATE_ORDERS, $fields);
    }

    /**
     * @param string $paymentSchema
     * @param string $paymentReference
     * @param array $fields
     * @return Resource
     * @throws Exception
     */
    public function createPayment($paymentSchema, $paymentReference, array $fields)
    {
        $fields['creditor'] = ['reference' => $this->options['creditor_reference']];

        if (Constants::PAYMENT_SCHEME_CARD == $paymentSchema) {
            $fields[Constants::ITEM_TYPE_CARD_ALIAS] = [
                'reference' => $paymentReference
            ];
        } else {
            $fields[Constants::ITEM_TYPE_MANDATE] = [
                'reference' => $paymentReference
            ];
        }

        return $this->doRequest('POST', Constants::FOLLOW_CREATE_PAYINS, $fields);
    }


    /**
     * @param string $paymentSchema
     * @param string $mandateReference
     * @param array $fields
     *
     * @return Resource
     * @throws Exception
     */
    public function refundPayment($paymentSchema, $mandateReference, array $fields)
    {
        $fields['creditor'] = ['reference' => $this->options['creditor_reference']];
        $fields['mandate'] = ['reference' => $mandateReference];
        $fields['scheme'] = $paymentSchema;

        return $this->doRequest('POST', Constants::FOLLOW_CREATE_PAYOUTS, $fields);
    }

    /**
     * @param $paymentId
     *
     * @return Resource
     * @throws Exception
     */
    public function getPayment($paymentId)
    {
        return $this->doRequest(
            'GET',
            Constants::FOLLOW_SEARCH_PAYMENT_BY_ID,
            null,
            null,
            ['id' => $paymentId]
        );
    }

    /**
     * @param $orderId
     *
     * @return Resource
     * @throws Exception
     */
    public function getOrder($orderId)
    {
        return $this->doRequest(
            'GET',
            Constants::FOLLOW_SEARCH_ORDER_BY_ID,
            null,
            null,
            ['id' => $orderId]
        );
    }

    /**
     * @param Resource $order
     *
     * @return Resource
     * @throws Exception
     */
    public function getOrderPaymentReference(Resource $order, $follow)
    {
        return $this->doRequest('GET', $follow, null, $order);
    }

    /**
     * @param Resource $mandate
     *
     * @return Resource
     * @throws Exception
     */
    public function getOrderBankAccount(Resource $mandate, $follow)
    {
        return $this->doRequest('GET', $follow, null, $mandate);
    }

    /**
     * @return string
     */
    public function getDefaultCheckoutMode()
    {
        return $this->options['default_checkout_mode'];
    }

    /**
     * @param Resource $order
     * @param string $iframeMode
     *
     * @return string
     * @throws Exception
     */
    public function getCheckoutIframe(Resource $order, $iframeMode)
    {
        $resource = $this->doRequest(
            'GET',
            Constants::FOLLOW_EXTENDED_USER_APPROVAL,
            null,
            $order,
            ['mode' => $iframeMode]
        );

        $html = $resource->getState()['content'];

        return base64_decode($html);
    }

    /**
     * @param Resource $order
     *
     * @return string
     * @throws LinkNotUniqueException
     * @throws RelNotFoundException
     */
    public function getCheckoutRedirect(Resource $order)
    {
        return $order->getLink($this->getRelationsNamespace() . Constants::FOLLOW_USER_APPROVAL)->getHref();
    }

    /**
     * @param string $method
     * @param string $follow
     * @param array|null $fields
     * @param Resource|null $resource
     * @param array|null $urlVariables
     *
     * @return Resource
     * @throws Exception
     */
    protected function doRequest(
        $method,
        $follow,
        array $fields = null,
        Resource $resource = null,
        array $urlVariables = null
    ) {
        $rel = new CustomRel($this->getRelationsNamespace() . $follow);

        $follow = new Follow($rel, $method, $urlVariables, $fields ? new JsonBody($fields) : null);

        return $this->hapiClient->sendFollow($follow, $resource);
    }

    /**
     * @return string
     */
    protected function getRelationsNamespace()
    {
        return Constants::RELATION_URI . '/alps#';
    }

    /**
     * @return string
     */
    protected function getApiEndpoint()
    {
        return $this->options['sandbox'] ?
            Constants::BASE_URI_SANDBOX :
            Constants::BASE_URI_PROD
        ;
    }
}
