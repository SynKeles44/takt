/*
 * The boot file. Every module wires itself through listeners on `document` or `window` and looks
 * its elements up at the moment an event arrives — never once at start. That is the one rule
 * this file enforces by shape: a node held from the first paint dies the moment a live form
 * swaps the region around it, and the sidebar's account menu went dead exactly that way.
 */
import { createBoard } from './board';
import { carousel } from './carousel';
import { clock } from './clock';
import { commandRunner } from './command-runner';
import { copyButtons } from './copy';
import { dayRange } from './day-range';
import { deferredRegions } from './deferred';
import { confirmDialog } from './dialog';
import { docker } from './docker';
import { filters } from './filters';
import { folderPicker } from './folder-picker';
import { formHelpers } from './forms';
import { frameProbe } from './fps';
import { liveForms } from './live-form';
import { slidingMarkers } from './marker';
import { menus, navToggle } from './menus';
import { motionLayer } from './motion';
import { notifications } from './notifications';
import { palette } from './palette';
import { partialLinks } from './partial';
import { pendingMarker } from './pending';
import { prefetchLinks } from './prefetch';
import { reviews } from './reviews';
import { shellApi } from './shell';
import { sidebarOrder } from './sidebar-order';
import { swapRegions } from './swap';
import { ticketBoard } from './ticket-board';
import { timeFields } from './time-field';
import { toast } from './toast';
import { asyncForms } from './todo-async';
import { updateNotice } from './update';

clock();
confirmDialog();
formHelpers();
carousel();
palette();
notifications();
shellApi();
updateNotice();
liveForms();
asyncForms();
menus();
navToggle();
copyButtons();
filters();
reviews();
partialLinks();

// the dashboard's edit mode; it re-reads the DOM after every region swap
createBoard({ swapRegions, toast }).apply();

dayRange();
folderPicker({ toast });
commandRunner({ toast });
docker({ swapRegions, toast });
ticketBoard({ swapRegions, toast });
motionLayer();
prefetchLinks();
pendingMarker();
deferredRegions({ swapRegions });
sidebarOrder();
timeFields();
slidingMarkers();
frameProbe();
