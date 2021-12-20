<?php

namespace App\Traits;

use http\Exception\InvalidArgumentException;
use Illuminate\Support\Facades\Crypt;
use Livewire\Exceptions\PropertyNotFoundException;

trait withRememberState
{
    /**
     * Max number of states ( forward or backward ) to keep track of
     *
     * @var int
     */
    public int $maxTrails = 10;

    /**
     * Component name. Class name will be attached
     *
     * @var string
     */
    public string $component = 'track';

    private string $mainTracker = "_livewire_component_states";

    /**
     * Delete tracker after a number of page refresh
     * x=3, meaning on the 3rd page refresh the tracker will be forgotten and component set back to the initial state
     *
     * @var int
     */
    public int $refreshAtXPageRefresh = 3;

    public function mountWithRememberState() :void
    {
        $this->trackRefresh();

        //create generate state array if one does exist. Will house states for all components
        if (!session()->has($this->mainTracker))
            session([$this->mainTracker=>[]]);
    }


    /**
     * Set variables that need to be tracked and also set their initial value
     * Everything starts with this function
     *
     * @param array $properties
     * @throws \Throwable
     */
    public function trackAndInit(array  $properties) :void
    {
        $this->setTracker();

        if (!empty($properties)){
            $trackedProperties =[];

            //Properties found in A(session) that are not in B(properties to track)
            //coder doesn't wish to track them anymore.Forget them
            if (session()->has( $this->mainTracker.'.'.$this->component.'.payload')) {
                $trackedProperties = $this->getPayload();
                $unTrackItems = array_diff_key($trackedProperties, $properties);
                if (!empty($unTrackItems))
                    foreach ($unTrackItems as $key => $value) {
                        unset($trackedProperties[$key]);
                    }
            }

            //add each property to be tracked to trackedProperties
            foreach ($properties as $param => $value){
                //unknown property, not found in class
                throw_if(!property_exists($this,$param),'PropertyNotFoundException',$param);
                if ( !array_key_exists( $param,$trackedProperties ) ){//track properties not being tracked
                    $trackedProperties[$param] = $value;
                }else{//restore state of currently tracked properties
                    $this->{$param} =$trackedProperties[$param];
                }
            }

            //json encode, encrypt and store tracked items in session
            $jsonTracks = json_encode($trackedProperties);
            session([$this->mainTracker.'.'.$this->component.'.payload' => Crypt::encrypt( $jsonTracks) ]);


        }else
            throw new InvalidArgumentException('Parameters to track not specified');

    }

    private function getClassName(object $obj) : string
    {
        $classNameArr = explode('\\',get_class($obj));
        return end($classNameArr);
    }

    /**
     * set component tracker var name used in session
     */
    private function setTracker() :void
    {
        $this->mainTracker.'.'.$this->component .= '_'.$this->getClassName($this);
        session([$this->mainTracker.'.'.$this->component.'.resetAfterRefresh' => $this->refreshAtXPageRefresh ]);
    }

    /**
     * track page refresh. Reset state after X number of refresh
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function trackRefresh() :void
    {
        $tracker = $this->mainTracker.'.'.$this->component;
        if (session()->has($tracker)){

            if(session()->has($tracker.'.resetAfterRefresh')){
                $currentRefresh = session()->get($tracker . '.pageRefresh') + 1;
                session([$tracker . '.pageRefresh' => $currentRefresh ]); //reset tracker, set all component to initial state
                if ($currentRefresh >= session()->get($tracker.'.resetAfterRefresh'))
                    $this->stopTracking();

            }
        }
    }



    /**
     *  Update property in session when updated on component
     *
     * @param string $name
     * @throws PropertyNotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function updatedWithRememberState(string $name) :void
    {
        $this->updateTrackedItems($name);
    }

    /**
     *update property(ies) being tracked in session when value change
     *
     * @param string|array $properties
     * @throws PropertyNotFoundException
     * @throws InvalidArgumentException
     * @throws \Throwable
     */
    public function updateTrackedItems(string|array $properties) :void
    {
        $trackedProperties = $this->getPayload(); //currently, tracked properties

        if (!empty($properties)) {
            if (is_string($properties)){
                throw_if(!property_exists($this, $properties), 'PropertyNotFoundException', $properties);
                $trackedProperties[$properties] = $this->{$properties};
            }elseif(is_array($properties)){
                foreach ($properties as $param) {
                    throw_if(!property_exists($this, $param), 'PropertyNotFoundException', $param);
                    $trackedProperties[$param] = $this->{$param};
                }
            }else{
                throw new InvalidArgumentException();
            }
        }

        $jsonTracks = json_encode($trackedProperties);
        //index used to move through states: -1 because we start at 0 array index of track.old
        $index = session()->has($this->mainTracker.'.'.$this->component.'.index') ? session()->get($this->mainTracker.'.'.$this->component.'.index') : -1;

        if (($index < $this->maxTrails && $index >= 0) || $index == -1) {//keep track of a max of z.B 5 states
            session()->push($this->mainTracker.'.'.$this->component.'.old', session()->get($this->mainTracker.'.'.$this->component.'.payload'));//save previous state;used to move backward or forward between states
            session([$this->mainTracker.'.'.$this->component.'.index' =>  $index + 1 ]);//first index 0
        }else{
            //number of states exceeded;reset track.old array and index
            session()->forget($this->mainTracker.'.'.$this->component.'.old');
            session([$this->mainTracker.'.'.$this->component.'.index' => 0]);
        }

        //save updated data as new payload
        session([$this->mainTracker.'.'.$this->component.'.payload' => Crypt::encrypt( $jsonTracks) ]);

    }

    /**
     * go to previous state
     *
     * @return void
     */
    public function goBackward():void
    {
        $this->traverseState('backward');
    }

    /**
     * go to next state after going to previous state
     *
     * @return void
     */
    public function goForward() :void
    {
        $this->traverseState('forward');
    }

    /**
     * go back to previous or forward state using payload store in track.old array
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function traverseState(string $backwardOrForward='backward') :void
    {
        //get current index value in session if one
        $index =session()->has($this->mainTracker.'.'.$this->component.'.index') ? session()->get($this->mainTracker.'.'.$this->component.'.index') : 0;

        if (session()->has($this->mainTracker.'.'.$this->component.'.old')) {
            //get old states
            $oldData = session()->get($this->mainTracker.'.'.$this->component.'.old');
            if (array_key_exists($index, $oldData)) { //requested state found

                if ($index >= 0 && $index <= $this->maxTrails){//index is within array keys

                    if (strtolower($backwardOrForward) =='backward') {
                        $index = $index == 0 ? 0 : $index-1;
                        //reset index to first element in array, so user can only go forward
                        session([$this->mainTracker.'.'.$this->component.'.index' => ($index < 0 ? 0 : $index) ]);
                    }
                    elseif(strtolower($backwardOrForward) =='forward') {
                        $index = $index + 1;
                        //prevent index from going out of array range
                        session([$this->mainTracker.'.'.$this->component.'.index' => ($index >= sizeof($oldData) ? sizeof($oldData) - 1 : $index) ]);
                    }

                    //previous state (could also be next state: depends on how u look at it)
                    //set requested state as payload
                    session([$this->mainTracker.'.'.$this->component.'.payload' => session()->get($this->mainTracker.'.'.$this->component.'.old.'. $index)]);

                }else{
                    //reset index to last element in array, so user can only go backwards from here
                    //last saved state reached i.e last element in array
                    session([$this->mainTracker.'.'.$this->component.'.index' => array_key_last(session()->get($this->mainTracker.'.'.$this->component.'.old'))]);
                }

            }else{ //requested state not found, set current state to the last known saved state
                $lastVisited = session()->get($this->mainTracker.'.'.$this->component.'.old');
                if (!empty($lastVisited)) {
                    //set new index to the key of last item in array, so we can keep go back to previously saved states
                    session([$this->mainTracker.'.'.$this->component.'.index'=> array_key_last($lastVisited)]);
                    $lastVisited = end($lastVisited);
                    session([$this->mainTracker.'.'.$this->component.'.payload' => $lastVisited]);
                }
            }
        }
        //refresh properties so that newly loaded state can reflect on properties
        $this->refreshProperties();
    }

    /**
     * get tracked vars decrypted
     *
     * @return array
     */
    private function getPayload() :array
    {
        return is_string(session()->get($this->mainTracker.'.'.$this->component.'.payload')) ?
            json_decode(Crypt::decrypt(session()->get($this->mainTracker.'.'.$this->component.'.payload')),true)
            : [];
    }

    /**
     * update properties stored in session with new data: function
     * is called when user moves backwards or forwards (after moving backwards)
     *
     * @return void
     */
    public function refreshProperties():void
    {
        $payloadEnc = session()->get($this->mainTracker.'.'.$this->component.'.payload');
        if (!empty($payloadEnc)) {
            $payload = json_decode( Crypt::decrypt($payloadEnc, true));
            foreach ($payload as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Stop tracking - forget tracker in session
     *
     * @return bool
     */
    public function stopTracking(): bool
    {
        session()->forget($this->mainTracker.'.'.$this->component);
        return true;
    }
}
