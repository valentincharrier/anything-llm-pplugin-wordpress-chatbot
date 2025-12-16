<?php
/**
 * Loader class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Loader
 */
class OFAC_Loader {

    /**
     * Actions to register
     *
     * @var array
     */
    protected $actions = array();

    /**
     * Filters to register
     *
     * @var array
     */
    protected $filters = array();

    /**
     * Add action
     *
     * @param string $hook          Hook name
     * @param object $component     Component
     * @param string $callback      Callback method
     * @param int    $priority      Priority
     * @param int    $accepted_args Accepted arguments
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add filter
     *
     * @param string $hook          Hook name
     * @param object $component     Component
     * @param string $callback      Callback method
     * @param int    $priority      Priority
     * @param int    $accepted_args Accepted arguments
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add hook
     *
     * @param array  $hooks         Hooks array
     * @param string $hook          Hook name
     * @param object $component     Component
     * @param string $callback      Callback method
     * @param int    $priority      Priority
     * @param int    $accepted_args Accepted arguments
     * @return array
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );
        return $hooks;
    }

    /**
     * Register all hooks
     */
    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
