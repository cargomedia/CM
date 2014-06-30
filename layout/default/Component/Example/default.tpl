<ul class="tabs menu-tabs example-navigation">
  {block name="tabs"}
    <li data-tab="components"><a href="{linkUrl page='CM_Page_Example' tab='components'}">Components</a></li>
    <li data-tab="menus"><a href="{linkUrl page='CM_Page_Example' tab='menus'}">Menus</a></li>
    <li data-tab="button"><a href="{linkUrl page='CM_Page_Example' tab='button'}">Buttons</a></li>
    <li data-tab="forms"><a href="{linkUrl page='CM_Page_Example' tab='forms'}">Forms</a></li>
    <li data-tab="variables"><a href="{linkUrl page='CM_Page_Example' tab='variables'}">Variables</a></li>
    <li data-tab="icons"><a href="{linkUrl page='CM_Page_Example' tab='icons'}">Icons</a></li>
  {/block}
</ul>

<div class="tabs-content">
  {block name="tabs-content"}
    <div>
      {viewTemplate name='tabs/example' foo=$foo now=$now}
    </div>
    <div>
      {code language="html5"}{load file='Component/Example/tabs/menus.tpl' namespace='CM' parse=false}{/code}
      {viewTemplate name='tabs/menus'}
    </div>
    <div>
      {code language="html5"}{load file='Component/Example/tabs/buttons.tpl' namespace='CM' parse=false}{/code}
      {viewTemplate name='tabs/buttons'}
    </div>
    <div>
      {code language="html5"}{load file='Component/Example/tabs/forms.tpl' namespace='CM' parse=false}{/code}
      {viewTemplate name='tabs/forms'}
    </div>
    <div>
      {viewTemplate name='tabs/variables' colorStyles=$colorStyles}
    </div>
    <div>
      {viewTemplate name='tabs/icons' icons=$icons}
    </div>
  {/block}
</div>
