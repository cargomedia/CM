{extends file=$render->getLayoutPath('FormField/Geometry_Vector2/default.tpl', 'CM')}

{block name='content' append}
  {tag el="input" name="{$name}[zCoordinate]" type="text" value=$z class="textinput"}
{/block}
