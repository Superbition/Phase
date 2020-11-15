<?php

namespace Polyel\Console;

class Input
{
    public string $command = '';

    public array $arguments = [];

    public array $options = [];

    public function __construct(array $argv, int $argc)
    {
        // Only process arguments if they exist after the script name
        if($argc > 1)
        {
            /*
             * Remove the script name and first argument in
             * order to check if the name of the command to run has
             * been given.
             */
            $command = array_splice($argv, 0, 2)[1];

            // Only assign a command if it is not an option as it is possible that no command is given, only options
            if($this->isNotAnOption($command))
            {
                $this->command = $command;
            }
            else
            {
                /*
                 * Else the first argument is not a command
                 * but an option, so we shift it back onto the
                 * argv array to be parsed. At this stage it means
                 * only options and or sub arguments were given.
                 */
                array_unshift($argv, $command);
            }

            // Send the rest of argv to be parsed and processed into command segments
            $this->parseCommandInput($argv);
        }

        //var_dump($argv);
    }

    private function parseCommandInput(array $argv)
    {
        // The different segments to split the command input into
        $parsedCommandSegments = [
            'arguments' => [],
            'options' => [],
        ];

        $optionIsWaitingForValue = false;

        foreach($argv as $key => $arg)
        {
            /*
             * Detect when an option is waiting for its value as the
             * flag $optionIsWaitingForValue will be set to true and the
             * current argument won't be an option. Or we will have
             * encountered an argument separator which will be '--'.
             */
            if(($optionIsWaitingForValue && $this->isNotAnOption($arg)) || $this->isArgumentSeparator($arg))
            {
                // Only assign the value to the option if the current argument is not an argument separator
                if($this->isNotArgumentSeparator($arg))
                {
                    // Get the last added option and set its value using the current argument value.
                    $lastAddedOption = array_key_last($parsedCommandSegments['options']);
                    $parsedCommandSegments['options'][$lastAddedOption] = $arg;
                }

                $optionIsWaitingForValue = false;

                /*
                 * We have collected the option's value or found an argument
                 * separator, we can now move onto the next argument in the array
                 */
                continue;
            }

            // Matches options that start with a - or --
            if($this->isAnOption($arg))
            {
                // Supports the use of -bar=value or --bar=value
                if(strpos($arg, '=') !== false)
                {
                    $arg = explode('=', $arg);

                    $parsedCommandSegments['options'][$arg[0]] = $arg[1];

                    continue;
                }

                // Supports the use of -bValue or -fValue etc.
                if(!strpos($arg, '=') !== false && strlen($arg) > 2 && $this->isAShortOption($arg))
                {
                    $option[0] = substr($arg, 0, 2);
                    $option[1] = substr($arg, 2, strlen($arg));

                    $parsedCommandSegments['options'][$option[0]] = $option[1];

                    continue;
                }

                /*
                 * If we get to this stage, it means we have a option
                 * using a space to indicate that the next argument in
                 * the array is its value e.g. --bar foo or -bar "foo bar" etc.
                 */
                $parsedCommandSegments['options'][$arg] = true;

                /*
                 * Set the flag that an option is waiting for its value
                 * so it can get picked up during the next loop cycle.
                 */
                $optionIsWaitingForValue = true;
                continue;
            }

            /*
             * At this stage we have a normal positional argument and not an option.
             * So we can store the argument and continue onto the next.
             */
            $parsedCommandSegments['arguments'][] = $arg;
            continue;
        }

        var_dump($parsedCommandSegments);
    }

    private function isAnOption($arg)
    {
        // Supports short and long options: -b or --bar etc.
        return $this->isAShortOption($arg) || $this->isALongOption($arg);
    }

    private function isNotAnOption($arg)
    {
        return !$this->isAnOption($arg);
    }

    private function isAShortOption($arg)
    {
        // Make sure the argument doesn't start with two hyphens but does start with one hyphen
        return strpos($arg, '--') !== 0 && strpos($arg, '-') === 0;
    }

    private function isALongOption($arg)
    {
        // Make sure the argument starts with two hyphens
        return strpos($arg, '--') === 0;
    }

    private function isArgumentSeparator($arg)
    {
        return $arg === '--';
    }

    private function isNotArgumentSeparator($arg)
    {
        return !$this->isArgumentSeparator($arg);
    }
}