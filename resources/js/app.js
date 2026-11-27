import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import nihamDataGrid from './components/datagrid';

Alpine.plugin(collapse);
Alpine.data('nihamDataGrid', nihamDataGrid);

window.Alpine = Alpine;

Alpine.start();
