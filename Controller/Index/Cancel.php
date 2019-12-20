<?php

namespace SwedbankPay\Payments\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository as MageOrderRepository;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Api\OrderRepositoryInterface as SwedbankOrderRepository;
use SwedbankPay\Payments\Api\QuoteRepositoryInterface as SwedbankQuoteRepository;
use SwedbankPay\Payments\Helper\Config as ConfigHelper;

/**
 * Class Cancel
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cancel extends PaymentActionAbstract implements CsrfAwareActionInterface
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var SwedbankQuoteRepository
     */
    protected $swedbankQuoteRepo;

    /**
     * @var SwedbankOrderRepository
     */
    protected $swedbankOrderRepo;

    /**
     * @var MageOrderRepository
     */
    protected $mageOrderRepo;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * Cancel constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param EventManager $eventManager
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param UrlInterface $urlInterface
     * @param CheckoutSession $checkoutSession
     * @param SwedbankQuoteRepository $swedbankQuoteRepo
     * @param SwedbankOrderRepository $swedbankOrderRepo
     * @param MageOrderRepository $mageOrderRepo
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        EventManager $eventManager,
        ConfigHelper $configHelper,
        Logger $logger,
        UrlInterface $urlInterface,
        CheckoutSession $checkoutSession,
        SwedbankQuoteRepository $swedbankQuoteRepo,
        SwedbankOrderRepository $swedbankOrderRepo,
        MageOrderRepository $mageOrderRepo
    ) {
        parent::__construct($context, $resultJsonFactory, $eventManager, $configHelper, $logger);

        $this->urlInterface = $urlInterface;
        $this->checkoutSession = $checkoutSession;
        $this->swedbankQuoteRepo = $swedbankQuoteRepo;
        $this->swedbankOrderRepo = $swedbankOrderRepo;
        $this->mageOrderRepo = $mageOrderRepo;

        $this->setEventName('cancel');
        $this->setEventMethod([$this, 'restoreQuote']);
    }

    /**
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function restoreQuote()
    {
        $this->logger->debug('Cancel controller is called');

        $this->checkoutSession->restoreQuote();

        /** @var \Magento\Quote\Model\Quote  */
        $quote = $this->checkoutSession->getQuote();

        $this->logger->debug(sprintf('Quote ID %s is restored', $quote->getEntityId()));

        $swedbankQuote = $this->swedbankQuoteRepo->getByQuoteId($quote->getEntityId());
        $swedbankOrder = $this->swedbankOrderRepo->getByPaymentId($swedbankQuote->getPaymentId());

        $order = $this->mageOrderRepo->get($swedbankOrder->getOrderId());

        $order->setState(Order::STATE_CANCELED);
        $order->setStatus(Order::STATE_CANCELED);

        $this->mageOrderRepo->save($order);

        $this->logger->debug(
            sprintf(
                'Order ID %s is updated to state \'%s\' & status \'%s\'',
                $order->getEntityId(),
                $order->getState(),
                $order->getStatus()
            )
        );

        $url = $this->urlInterface->getUrl('checkout/cart');
        $this->setRedirect($url);
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
