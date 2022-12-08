<?php
namespace Psmb\Newsletter\View;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Locale;
use Neos\Flow\I18n\Service;
use Neos\Flow\Mvc\View\AbstractView;
use Neos\Flow\Security\Context;
use Neos\Fusion\Core\Runtime;
use Neos\Fusion\Exception\RuntimeException;
use Neos\Neos\Domain\Service\FusionService;
use Neos\Neos\Exception;

/**
 * A TypoScript view
 */
class FusionView extends AbstractView
{
    /**
     * @Flow\Inject
     * @var Service
     */
    protected $i18nService;

    /**
     * This contains the supported options, their default values, descriptions and types.
     *
     * @var array
     */
    protected $supportedOptions = array(
        'enableContentCache' => array(null, 'Flag to enable content caching inside TypoScript (overriding the global setting).', 'boolean')
    );

    /**
     * @Flow\Inject
     * @var FusionService
     */
    protected $fusionService;

    /**
     * The TypoScript path to use for rendering the node given in "value", defaults to "page".
     *
     * @var string
     */
    protected $fusionPath = 'newsletter';

    /**
     * @var Runtime
     */
    protected $fusionRuntime;

    /**
     * @Flow\Inject
     * @var Context
     */
    protected $securityContext;

    /**
     * Renders the view
     *
     * @return mixed The rendered view
     * @throws \Exception if no node is given
     * @api
     */
    public function render()
    {
        $contextVars = $this->variables['value'];
        if (!is_array($contextVars)) {
            throw new Exception('FusionView needs an array for variable \'value\'.', 1329736457);
        }
        $siteNode = $contextVars['site'];
        if (!$siteNode instanceof Node) {
            throw new Exception('FusionView needs a site node to be set in context variables passed to \'value\'.', 1329736457);
        }
        $fusionRuntime = $this->getFusionRuntime($siteNode);

        $dimensions = $siteNode->getContext()->getDimensions();
        if (array_key_exists('language', $dimensions) && $dimensions['language'] !== array()) {
            $currentLocale = new Locale($dimensions['language'][0]);
            $this->i18nService->getConfiguration()->setCurrentLocale($currentLocale);
            $this->i18nService->getConfiguration()->setFallbackRule(array('strict' => false, 'order' => array_reverse($dimensions['language'])));
        }

        $fusionRuntime->pushContextArray($contextVars);
        try {
            $output = $fusionRuntime->render($this->fusionPath);
        } catch (RuntimeException $exception) {
            throw $exception->getPrevious();
        }
        $fusionRuntime->popContext();

        return $output;
    }

    /**
     * @param TraversableNodeInterface $siteNode
     * @return Runtime
     */
    protected function getFusionRuntime(TraversableNodeInterface $siteNode): Runtime
    {
        if ($this->fusionRuntime === null) {
            $this->fusionRuntime = $this->fusionService->createRuntime($siteNode, $this->controllerContext);

            if (isset($this->options['enableContentCache']) && $this->options['enableContentCache'] !== null) {
                $this->fusionRuntime->setEnableContentCache($this->options['enableContentCache']);
            }
        }
        return $this->fusionRuntime;
    }

    /**
     * Clear the cached runtime instance on assignment of variables
     *
     * @param string $key
     * @param mixed $value
     * @return AbstractView
     */
    public function assign($key, $value)
    {
        $this->fusionRuntime = null;
        return parent::assign($key, $value);
    }
}
