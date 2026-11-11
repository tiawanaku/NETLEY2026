<?php

// Solo "Guardar" en todo el sistema (Filament trae "Guardar cambios").
return ['builder' => ['actions' => ['edit' => ['modal' => ['actions' => ['save' => ['label' => 'Guardar']]]]]]];
