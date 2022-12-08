<?php
namespace Psmb\Newsletter\Service;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Flowpack\JobQueue\Common\Annotations as Job;
use Neos\Flow\Mvc\ActionResponse;
use Psmb\Newsletter\Domain\Model\Subscriber;
use Psmb\Newsletter\View\FusionView;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Neos\Service\LinkingService;
use Neos\SwiftMailer\Message;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Mvc\Controller\Arguments;


/**
 * @Flow\Scope("singleton")
 */
class FusionMailService
{
    /**
     * @var ControllerContext
     */
    protected $controllerContext;

    /**
     * @Flow\Inject
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var FusionView
     */
    protected $view;

    /**
     * @Flow\Inject
     * @var LinkingService
     */
    protected $linkingService;

    /**
     * @Flow\InjectConfiguration(path="globalSettings")
     * @var string
     */
    protected $globalSettings;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="http.baseUri")
     * @var string
     */
    protected $baseUri;

    /**
     * We can't do this in constructor as we need configuration to be injected
     */
    public function initializeObject()
    {
        $request = $this->createRequest();
        $this->controllerContext = $this->createControllerContext($request);
        $this->view->setControllerContext($this->controllerContext);
        $this->uriBuilder->setRequest($request);
    }

    /**
     * @return ActionRequest
     */
    protected function createRequest(): ActionRequest
    {
        $_SERVER['FLOW_REWRITEURLS'] = 1;
        $baseUri = new Uri($this->baseUri);
        $httpRequest = ServerRequest::fromGlobals();
        $httpRequest = $httpRequest->withUri($baseUri);
        $request = ActionRequest::fromHttpRequest($httpRequest);
        $request->setFormat('html');
        return $request;
    }

    /**
     * Creates a controller content context for live dimension
     *
     * @param ActionRequest $request
     * @return ControllerContext
     */
    protected function createControllerContext(ActionRequest $request): ControllerContext
    {
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($request);
        return new ControllerContext(
            $request,
            new ActionResponse(),
            new Arguments([]),
            $uriBuilder
        );
    }

    /**
     * Just a simple wrapper over SwiftMailer
     *
     * @param array $letter
     * @throws \Exception
     */
    public function sendLetter(array $letter)
    {
        $subject = $letter['subject'] ?? null;
        $body = $letter['body'] ?? null;
        $recipientAddress = $letter['recipientAddress'] ?? null;
        $recipientName = $letter['recipientName'] ?? null;
        $senderAddress = $letter['senderAddress'] ?? null;
        $senderName = $letter['senderName'] ?? null;
        $replyToAddress = $letter['replyToAddress'] ?? null;
        $carbonCopyAddress = $letter['carbonCopyAddress'] ?? null;
        $blindCarbonCopyAddress = $letter['blindCarbonCopyAddress'] ?? null;
        $format = $letter['format'] ?? null;

        if (!$subject) {
            throw new \Exception('"subject" must be set.', 1327060321);
        }
        if (!$recipientAddress) {
            throw new \Exception('"recipientAddress" must be set.', 1327060201);
        }
        if (!$senderAddress) {
            throw new \Exception('"senderAddress" must be set.', 1327060211);
        }

        $mail = new Message();
        $mail
            ->setFrom(array($senderAddress => $senderName))
            ->setTo(array($recipientAddress => $recipientName))
            ->setSubject($subject);
        if ($replyToAddress) {
            $mail->setReplyTo($replyToAddress);
        }
        if ($carbonCopyAddress) {
            $mail->setCc($carbonCopyAddress);
        }
        if ($blindCarbonCopyAddress) {
            $mail->setBcc($blindCarbonCopyAddress);
        }
        if ($format === 'plaintext') {
            $mail->setBody($body, 'text/plain');
        } else {
            $mail->setBody($body, 'text/html');
        }
        $mail->send();
    }

    /**
     * Send activation letter to confirm the new subscriber
     *
     * @Job\Defer(queueName="psmb-newsletter")
     * @param Subscriber $subscriber
     * @param string $hash
     * @return void
     */
    public function sendActivationLetter(Subscriber $subscriber, string $hash)
    {
        $siteNode = $this->getSiteNode();
        $arguments = ['--newsletter' => [
            '@package' => 'Psmb.Newsletter',
            '@controller' => 'Subscription',
            '@action' => 'confirm',
            'hash' => $hash
        ]];
        $activationLink = $this->linkingService->createNodeUri(
            $this->controllerContext,
            $siteNode,
            $siteNode,
            'html',
            true,
            $arguments
        );

        $this->view->assign('value', [
            'site' => $siteNode,
            'documentNode' => $siteNode,
            'node' => $siteNode,
            'subscriber' => $subscriber,
            'globalSettings' => $this->globalSettings,
            'activationLink' => $activationLink
        ]);
        $letter = $this->view->render();
        $this->sendLetter($letter);
    }

    /**
     * Generate a letter for given subscriber and subscription
     *
     * @param array|Subscriber $subscriber
     * @param array $subscription
     * @param NodeInterface|null $node
     * @return array
     */
    public function generateSubscriptionLetter($subscriber, array $subscription, NodeInterface $node = NULL): array
    {
        $dimensions = $subscription['dimensions'] ?? [];
        $siteNode = $this->getSiteNode($dimensions);
        $node = $node ?: $siteNode;
        $this->view->assign('value', [
            'site' => $siteNode,
            'documentNode' => $node,
            'node' => $node,
            'subscriber' => $subscriber,
            'subscription' => $subscription,
            'globalSettings' => $this->globalSettings
        ]);
        return $this->view->render();
    }

    /**
     * Generate a letter for given subscriber and subscription and sends it. Async.
     *
     * @Job\Defer(queueName="psmb-newsletter")
     * @param array|Subscriber $subscriber
     * @param array $subscription
     * @param NodeInterface|null $node
     * @return void
     */
    public function generateSubscriptionLetterAndSend($subscriber, array $subscription, NodeInterface $node = NULL)
    {
        $letter = $this->generateSubscriptionLetter($subscriber, $subscription, $node);
        if ($letter) {
            $this->sendLetter($letter);
        }
    }

    /**
     * @param array $dimensions
     * @return Node
     */
    protected function getSiteNode(array $dimensions = []): Node
    {
        $contextProperties = array(
            'workspaceName' => 'live',
            'dimensions' => $dimensions,
            'invisibleContentShown' => false,
            'inaccessibleContentShown' => false
        );
        $context = $this->contextFactory->create($contextProperties);
        return $context->getCurrentSiteNode();
    }
}
