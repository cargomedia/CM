{tag el="input" name=$name type="hidden" value="0"}
{tag el="input" type="checkbox" id=$id name=$name tabindex=$tabindex value="1" checked=$checked}
<label for="{$id}">{block name='label-prepend'}{/block}{if isset($text)}{$text}{/if}</label>
